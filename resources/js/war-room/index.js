import ForceGraph3D from '3d-force-graph';
import SpriteText from 'three-spritetext';
import * as THREE from 'three';
import { UnrealBloomPass } from 'three/examples/jsm/postprocessing/UnrealBloomPass.js';

/**
 * The 3D "command room" behind the AI team map: assistants as glowing cores
 * in a dark volume, consultations and hand-overs as light pulses travelling
 * along the links. Mounted by resources/views/filament/pages/ai-team-map.blade.php
 * and bundled on its own (npm run build:war-room) into public/vendor/war-room.
 */

const KIND_COLORS = { consult: '#38bdf8', handoff: '#fbbf24' };

const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function glowTexture() {
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = 128;
    const ctx = canvas.getContext('2d');
    const gradient = ctx.createRadialGradient(64, 64, 0, 64, 64, 64);
    gradient.addColorStop(0, 'rgba(255,255,255,0.9)');
    gradient.addColorStop(0.25, 'rgba(255,255,255,0.35)');
    gradient.addColorStop(1, 'rgba(255,255,255,0)');
    ctx.fillStyle = gradient;
    ctx.fillRect(0, 0, 128, 128);

    return new THREE.CanvasTexture(canvas);
}

function buildNode(node, glow) {
    const group = new THREE.Group();
    const color = new THREE.Color(node.color);
    const isManager = node.id === 'manager';
    const radius = isManager ? 10 : 6.5;

    const core = new THREE.Mesh(
        isManager ? new THREE.OctahedronGeometry(radius) : new THREE.IcosahedronGeometry(radius, 1),
        new THREE.MeshBasicMaterial({ color, transparent: true, opacity: 0.95 }),
    );
    const shell = new THREE.Mesh(
        new THREE.IcosahedronGeometry(radius * 1.55, 1),
        new THREE.MeshBasicMaterial({ color, wireframe: true, transparent: true, opacity: 0.35 }),
    );
    const halo = new THREE.Sprite(new THREE.SpriteMaterial({ map: glow, color, transparent: true, opacity: node.live ? 0.9 : 0.35, blending: THREE.AdditiveBlending, depthWrite: false }));
    halo.scale.set(radius * 5, radius * 5, 1);

    group.add(core, shell, halo);

    if (isManager) {
        const ring = new THREE.Mesh(new THREE.TorusGeometry(radius * 2.1, 0.35, 8, 72), new THREE.MeshBasicMaterial({ color, transparent: true, opacity: 0.7 }));
        ring.rotation.x = Math.PI / 2.4;
        group.add(ring);
        group.userData.ring = ring;
    }

    const label = new SpriteText(node.label, isManager ? 7 : 5.5, '#e2e8f0');
    label.fontFace = 'Vazirmatn, sans-serif';
    label.fontWeight = '600';
    label.material.depthWrite = false;
    label.position.y = -(radius * 2.2 + 3);
    group.add(label);

    group.userData = { ...group.userData, core, shell, halo, live: node.live, radius };

    return group;
}

export function mount(element, { onSelect }) {
    const glow = glowTexture();
    const motion = !reducedMotion();
    let spinning = motion;
    let autoFit = true;

    const graph = ForceGraph3D({ controlType: 'orbit' })(element)
        .backgroundColor('rgba(0,0,0,0)')
        .showNavInfo(false)
        .nodeLabel(() => '')
        .nodeThreeObject((node) => buildNode(node, glow))
        .nodeThreeObjectExtend(false)
        .linkColor((link) => KIND_COLORS[link.kind] ?? '#64748b')
        .linkOpacity(0.55)
        .linkWidth((link) => Math.min(0.4 + link.count * 0.25, 2.4))
        .linkCurvature((link) => (link.source.id < link.target.id ? 0.18 : -0.18))
        .linkDirectionalParticles((link) => (link.live ? 5 : Math.min(link.count, 2)))
        .linkDirectionalParticleSpeed((link) => (link.live ? 0.012 : 0.004))
        .linkDirectionalParticleWidth((link) => (link.live ? 2.6 : 1.4))
        .linkDirectionalArrowLength(4)
        .linkDirectionalArrowRelPos(1)
        .d3VelocityDecay(0.35)
        .cooldownTicks(120)
        .onEngineStop(() => {
            if (autoFit) {
                graph.zoomToFit(900, 190);
            }
        })
        .onNodeClick((node) => {
            spinning = false;
            autoFit = false;
            const distance = 90;
            const ratio = 1 + distance / Math.hypot(node.x, node.y, node.z || 1);
            graph.cameraPosition({ x: node.x * ratio, y: node.y * ratio, z: node.z * ratio }, node, 900);
            onSelect(node.id);
        })
        .onBackgroundClick(() => {
            onSelect(null);
        });

    graph.cameraPosition({ x: 0, y: 30, z: 240 });
    graph.d3Force('charge').strength(-260);
    graph.d3Force('link').distance(70);

    const bloom = new UnrealBloomPass(new THREE.Vector2(element.clientWidth, element.clientHeight), 0.55, 0.5, 0.3);
    graph.postProcessingComposer().addPass(bloom);

    const scene = graph.scene();
    const floor = new THREE.GridHelper(520, 26, 0x1e3a5f, 0x12263f);
    floor.position.y = -120;
    floor.material.transparent = true;
    floor.material.opacity = 0.55;
    scene.add(floor);

    const stars = new THREE.BufferGeometry();
    const positions = new Float32Array(900 * 3);
    for (let i = 0; i < positions.length; i++) {
        positions[i] = (Math.random() - 0.5) * 1400;
    }
    stars.setAttribute('position', new THREE.BufferAttribute(positions, 3));
    const dust = new THREE.Points(stars, new THREE.PointsMaterial({ color: 0x7dd3fc, size: 1.4, transparent: true, opacity: 0.45, depthWrite: false }));
    scene.add(dust);

    const controls = graph.controls();
    controls.enableDamping = true;
    controls.addEventListener('start', () => {
        spinning = false;
        autoFit = false;
    });

    let frame = null;
    let tick = 0;
    const animate = () => {
        tick += 0.04;
        if (spinning) {
            const { x, z } = graph.cameraPosition();
            const radius = Math.hypot(x, z);
            const theta = Math.atan2(x, z) + 0.0016;
            graph.cameraPosition({ x: radius * Math.sin(theta), z: radius * Math.cos(theta) });
        }
        if (motion) {
            dust.rotation.y += 0.0004;
            graph.graphData().nodes.forEach((node) => {
                const data = node.__threeObj?.userData;
                if (!data) {
                    return;
                }
                data.shell.rotation.y += 0.006;
                data.shell.rotation.x += 0.003;
                if (data.ring) {
                    data.ring.rotation.z += 0.012;
                }
                if (data.live) {
                    const pulse = 1 + 0.12 * Math.sin(tick + node.index);
                    data.core.scale.setScalar(pulse);
                    data.halo.material.opacity = 0.65 + 0.25 * Math.sin(tick * 1.3 + node.index);
                }
            });
        }
        frame = requestAnimationFrame(animate);
    };
    animate();

    const resize = new ResizeObserver(() => {
        graph.width(element.clientWidth).height(element.clientHeight);
        bloom.setSize(element.clientWidth, element.clientHeight);
    });
    resize.observe(element);
    graph.width(element.clientWidth).height(element.clientHeight);

    const toGraph = (data) => {
        const previous = new Map(graph.graphData().nodes.map((node) => [node.id, node]));

        return {
            nodes: data.nodes.map((node) => Object.assign(previous.get(node.id) ?? {}, node, node.id === 'manager' ? { fx: 0, fy: 0, fz: 0 } : {})),
            links: data.edges.map((edge) => ({ ...edge })),
        };
    };

    graph.graphData(toGraph({ nodes: [], edges: [] }));

    return {
        update(data) {
            graph.graphData(toGraph(data));
            [700, 2200].forEach((delay) => setTimeout(() => autoFit && graph.zoomToFit(600, 190), delay));
            graph.cooldownTicks(120);
            graph.d3ReheatSimulation();
        },
        focus(id) {
            const node = graph.graphData().nodes.find((candidate) => candidate.id === id);
            if (!node) {
                return;
            }
            spinning = false;
            autoFit = false;
            const ratio = 1 + 90 / Math.hypot(node.x, node.y, node.z || 1);
            graph.cameraPosition({ x: node.x * ratio, y: node.y * ratio, z: node.z * ratio }, node, 900);
        },
        reset() {
            spinning = motion;
            autoFit = true;
            graph.zoomToFit(900, 190);
        },
        destroy() {
            cancelAnimationFrame(frame);
            resize.disconnect();
            graph._destructor?.();
        },
    };
}
