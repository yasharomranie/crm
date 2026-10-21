import ForceGraph3D from '3d-force-graph';
import SpriteText from 'three-spritetext';
import * as THREE from 'three';

/**
 * The 3D "command room" behind the AI team map: assistants as flat avatar
 * badges in a dark volume, consultations and hand-overs as dots travelling
 * along the links. Mounted by resources/views/filament/pages/ai-team-map.blade.php
 * and bundled on its own (npm run build:war-room) into public/vendor/war-room.
 */

const KIND_COLORS = { consult: '#38bdf8', handoff: '#fbbf24' };

const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function ringTexture() {
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = 256;
    const ctx = canvas.getContext('2d');
    ctx.lineWidth = 10;
    ctx.strokeStyle = '#ffffff';
    ctx.beginPath();
    ctx.arc(128, 128, 116, 0, 2 * Math.PI);
    ctx.stroke();

    return new THREE.CanvasTexture(canvas);
}

const avatarCache = new Map();

function shapePath(ctx, shape, cx, cy, r) {
    const polygon = (sides, rotation = -Math.PI / 2) => {
        for (let i = 0; i < sides; i++) {
            const angle = rotation + (2 * Math.PI * i) / sides;
            const x = cx + r * Math.cos(angle);
            const y = cy + r * Math.sin(angle);
            i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
        }
        ctx.closePath();
    };

    ctx.beginPath();
    switch (shape) {
        case 'rounded-square': ctx.roundRect(cx - r * 0.85, cy - r * 0.85, r * 1.7, r * 1.7, r * 0.35); break;
        case 'hexagon': polygon(6, 0); break;
        case 'pentagon': polygon(5); break;
        case 'triangle': polygon(3); break;
        case 'diamond': polygon(4); break;
        case 'manager': polygon(8, Math.PI / 8); break;
        case 'teardrop':
            ctx.moveTo(cx, cy - r * 1.05);
            ctx.bezierCurveTo(cx + r * 0.2, cy - r * 0.6, cx + r * 0.85, cy - r * 0.15, cx + r * 0.85, cy + r * 0.2);
            ctx.arc(cx, cy + r * 0.2, r * 0.85, 0, Math.PI, false);
            ctx.bezierCurveTo(cx - r * 0.85, cy - r * 0.15, cx - r * 0.2, cy - r * 0.6, cx, cy - r * 1.05);
            break;
        case 'cloud':
            [[-0.45, 0.15, 0.5], [0.05, -0.2, 0.55], [0.5, 0.2, 0.45], [0, 0.3, 0.55]].forEach(([dx, dy, k]) => {
                ctx.moveTo(cx + r * (dx + k), cy + r * dy);
                ctx.arc(cx + r * dx, cy + r * dy, r * k, 0, 2 * Math.PI);
            });
            break;
        case 'flower':
            [[0, -0.5], [0.5, 0], [0, 0.5], [-0.5, 0]].forEach(([dx, dy]) => {
                ctx.moveTo(cx + r * (dx + 0.5), cy + r * dy);
                ctx.arc(cx + r * dx, cy + r * dy, r * 0.5, 0, 2 * Math.PI);
            });
            break;
        default: ctx.arc(cx, cy, r * 0.92, 0, 2 * Math.PI);
    }
}

/** The assistant's own avatar (its shape and colour) drawn as a glossy badge. */
function avatarTexture(shape, color) {
    const key = `${shape}|${color}`;
    if (avatarCache.has(key)) {
        return avatarCache.get(key);
    }

    const size = 256;
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = size;
    const ctx = canvas.getContext('2d');
    const c = size / 2;
    const r = size * 0.4;

    shapePath(ctx, shape, c, c, r);
    ctx.fillStyle = color;
    ctx.fill();
    ctx.lineWidth = 8;
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#0b1220';
    ctx.stroke();

    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    avatarCache.set(key, texture);

    return texture;
}

function buildNode(node, ringMap) {
    const group = new THREE.Group();
    const color = new THREE.Color(node.color);
    const isManager = node.id === 'manager';
    const radius = isManager ? 12 : 8;
    const base = radius * 3;

    const core = new THREE.Sprite(new THREE.SpriteMaterial({ map: avatarTexture(node.shape, node.color), transparent: true, depthWrite: false }));
    core.scale.set(base, base, 1);
    const halo = new THREE.Sprite(new THREE.SpriteMaterial({ map: ringMap, color, transparent: true, opacity: node.live ? 1 : 0, depthWrite: false }));
    halo.scale.set(base * 1.35, base * 1.35, 1);

    group.add(halo, core);

    if (isManager) {
        const ring = new THREE.Mesh(new THREE.TorusGeometry(radius * 1.9, 0.3, 8, 72), new THREE.MeshBasicMaterial({ color, transparent: true, opacity: 0.7 }));
        ring.rotation.x = Math.PI / 2.4;
        group.add(ring);
        group.userData.ring = ring;
    }

    const label = new SpriteText(node.label, isManager ? 7 : 5.5, '#e2e8f0');
    label.fontFace = 'Vazirmatn, sans-serif';
    label.fontWeight = '600';
    label.material.depthWrite = false;
    label.position.y = -(radius * 1.6 + 4);
    group.add(label);

    group.userData = { ...group.userData, core, halo, base, live: node.live };

    return group;
}

export function mount(element, { onSelect }) {
    const ringMap = ringTexture();
    const motion = !reducedMotion();
    let spinning = motion;
    let autoFit = true;

    const graph = ForceGraph3D({ controlType: 'orbit' })(element)
        .backgroundColor('rgba(0,0,0,0)')
        .showNavInfo(false)
        .nodeLabel(() => '')
        .nodeThreeObject((node) => buildNode(node, ringMap))
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
                graph.zoomToFit(900, 120);
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

    const scene = graph.scene();
    const floor = new THREE.GridHelper(520, 26, 0x23344d, 0x1a2638);
    floor.position.y = -120;
    floor.material.transparent = true;
    floor.material.opacity = 0.55;
    scene.add(floor);

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
            graph.graphData().nodes.forEach((node) => {
                const data = node.__threeObj?.userData;
                if (!data) {
                    return;
                }
                if (data.ring) {
                    data.ring.rotation.z += 0.012;
                }
                if (data.live) {
                    const pulse = 1 + 0.12 * Math.sin(tick + node.index);
                    data.core.scale.set(data.base * pulse, data.base * pulse, 1);
                    data.halo.scale.set(data.base * (1.3 + 0.08 * Math.sin(tick * 1.3 + node.index)), data.base * (1.3 + 0.08 * Math.sin(tick * 1.3 + node.index)), 1);
                }
            });
        }
        frame = requestAnimationFrame(animate);
    };
    animate();

    const resize = new ResizeObserver(() => {
        graph.width(element.clientWidth).height(element.clientHeight);
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
            [700, 2200].forEach((delay) => setTimeout(() => autoFit && graph.zoomToFit(600, 120), delay));
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
            graph.zoomToFit(900, 120);
        },
        destroy() {
            cancelAnimationFrame(frame);
            resize.disconnect();
            graph._destructor?.();
        },
    };
}
