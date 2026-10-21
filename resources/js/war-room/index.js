import ForceGraph3D from '3d-force-graph';
import SpriteText from 'three-spritetext';
import * as THREE from 'three';

/**
 * The 3D "command room" behind the AI team map: assistants as flat avatar
 * badges in a dark volume, consultations and hand-overs as dots travelling
 * along the links. Mounted by resources/views/filament/pages/ai-team-map.blade.php
 * and bundled on its own (npm run build:war-room) into public/vendor/war-room.
 */

const KIND_COLORS = { consult: '#38bdf8', handoff: '#fbbf24', report: '#34d399', review: '#fb7185' };
const ROLE_COLORS = { coordinator: '#fbbf24', specialist: '#38bdf8', critic: '#fb7185' };

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
    // Corners are always rounded: the badges read as soft toys, not cut-outs.
    const polygon = (sides, rotation = -Math.PI / 2, radius = 0.3) => {
        const points = Array.from({ length: sides }, (_, i) => {
            const angle = rotation + (2 * Math.PI * i) / sides;

            return [cx + r * Math.cos(angle), cy + r * Math.sin(angle)];
        });
        const mid = (p, q) => [(p[0] + q[0]) / 2, (p[1] + q[1]) / 2];
        const start = mid(points[0], points[1]);
        ctx.moveTo(start[0], start[1]);
        for (let i = 1; i <= sides; i++) {
            const corner = points[i % sides];
            const next = mid(corner, points[(i + 1) % sides]);
            ctx.arcTo(corner[0], corner[1], next[0], next[1], r * radius * (sides <= 4 ? 1.1 : 1.5));
        }
        ctx.closePath();
    };

    ctx.beginPath();
    switch (shape) {
        case 'rounded-square': ctx.roundRect(cx - r * 0.88, cy - r * 0.88, r * 1.76, r * 1.76, r * 0.5); break;
        case 'hexagon': polygon(6, 0); break;
        case 'pentagon': polygon(5); break;
        case 'triangle': polygon(3, -Math.PI / 2, 0.45); break;
        case 'diamond': polygon(4, -Math.PI / 2, 0.4); break;
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

const mix = (hex, target, amount) => {
    const from = new THREE.Color(hex);
    const to = new THREE.Color(target);

    return `#${from.lerp(to, amount).getHexString()}`;
};

const INK = '#0b1220';

/** A noodle limb: a dark outline stroke with the limb colour on top, ending in a round hand/foot. */
function limb(ctx, color, from, to, width, end) {
    ctx.lineCap = 'round';
    [[INK, width + 8], [color, width]].forEach(([stroke, lineWidth]) => {
        ctx.strokeStyle = stroke;
        ctx.lineWidth = lineWidth;
        ctx.beginPath();
        ctx.moveTo(from[0], from[1]);
        ctx.lineTo(to[0], to[1]);
        ctx.stroke();
    });
    if (end === 'foot') {
        ctx.fillStyle = color;
        ctx.strokeStyle = INK;
        ctx.lineWidth = 5;
        ctx.beginPath();
        ctx.ellipse(to[0] + (to[0] < 128 ? -5 : 5), to[1] + 3, width * 0.95, width * 0.6, 0, 0, 2 * Math.PI);
        ctx.fill();
        ctx.stroke();
    }
}

/**
 * The robot look: the assistant's shape and colour as a little robot with
 * arms, legs, an antenna, ear bolts and a screen face (LED eyes, a speaker
 * grille). `state` is the expression (idle, blink, talk), `pose` the arm
 * position (0 resting, 1 waving).
 */
function robotTexture(shape, color, state, isManager, pose) {
    const size = 256;
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = size;
    const ctx = canvas.getContext('2d');
    const c = size / 2;
    const r = 70;
    const cy = c - 14;
    const limbColor = mix(color, INK, 0.12);
    const faceY = shape === 'triangle' ? r * 0.28 : shape === 'teardrop' ? r * 0.12 : 0;
    const narrow = shape === 'triangle' || shape === 'diamond';
    const sideX = narrow ? r * 0.62 : r * 0.9;
    const baseY = shape === 'triangle' ? cy + r * 0.62 : shape === 'diamond' ? cy + r * 0.75 : cy + r * 0.82;

    ctx.fillStyle = 'rgba(0,0,0,0.32)';
    ctx.beginPath();
    ctx.ellipse(c, baseY + 40, r * 0.8, 9, 0, 0, 2 * Math.PI);
    ctx.fill();

    [-1, 1].forEach((side) => limb(ctx, limbColor, [c + side * r * 0.36, baseY - 4], [c + side * r * 0.42, baseY + 30], 15, 'foot'));
    [-1, 1].forEach((side) => {
        const from = [c + side * sideX, cy + r * 0.12];
        const waving = pose === 1 && side === 1;
        const to = waving ? [c + side * (sideX + 24), cy - r * 0.78] : [c + side * (sideX + 26), cy + r * 0.62];
        limb(ctx, limbColor, from, to, 13);
        ctx.fillStyle = limbColor;
        ctx.strokeStyle = INK;
        ctx.lineWidth = 5;
        ctx.beginPath();
        ctx.arc(to[0], to[1], 11, 0, 2 * Math.PI);
        ctx.fill();
        ctx.stroke();
    });

    {
        const earX = narrow ? r * 0.62 : r * 0.95;
        ctx.fillStyle = mix(color, INK, 0.35);
        ctx.strokeStyle = INK;
        ctx.lineWidth = 5;
        [-1, 1].forEach((side) => {
            ctx.beginPath();
            ctx.roundRect(c + side * earX - 9, cy - 16 + (shape === 'triangle' ? r * 0.3 : 0), 18, 32, 8);
            ctx.fill();
            ctx.stroke();
        });
    }

    {
        ctx.strokeStyle = INK;
        ctx.lineWidth = 6;
        ctx.beginPath();
        ctx.moveTo(c, cy - r * 0.92);
        ctx.lineTo(c, cy - r * 1.22);
        ctx.stroke();
        ctx.fillStyle = color;
        ctx.beginPath();
        ctx.arc(c, cy - r * 1.3, 9, 0, 2 * Math.PI);
        ctx.fill();
        ctx.stroke();
    }

    shapePath(ctx, shape, c, cy, r);
    const body = ctx.createRadialGradient(c - r * 0.35, cy - r * 0.4, r * 0.1, c, cy, r * 1.15);
    body.addColorStop(0, mix(color, '#ffffff', 0.3));
    body.addColorStop(0.55, color);
    body.addColorStop(1, mix(color, INK, 0.38));
    ctx.fillStyle = body;
    ctx.fill();
    ctx.lineWidth = 6;
    ctx.lineJoin = 'round';
    ctx.strokeStyle = INK;
    ctx.stroke();

    const lit = mix(color, '#ffffff', 0.75);

    {
        const sw = r * 1.32;
        const sh = r * 0.86;
        const sy = cy - r * 0.04 + faceY;
        ctx.fillStyle = INK;
        ctx.beginPath();
        ctx.roundRect(c - sw / 2, sy - sh / 2, sw, sh, 24);
        ctx.fill();
        ctx.lineWidth = 3;
        ctx.strokeStyle = mix(color, '#ffffff', 0.35);
        ctx.stroke();

        [-1, 1].forEach((side) => {
            const x = c + side * r * 0.3;
            const y = sy - sh * 0.1;
            ctx.fillStyle = lit;
            ctx.beginPath();
            if (state === 'blink') {
                ctx.roundRect(x - r * 0.15, y - 3, r * 0.3, 6, 3);
            } else {
                ctx.roundRect(x - r * 0.15, y - r * 0.19, r * 0.3, r * 0.38, 10);
            }
            ctx.fill();
        });

        ctx.fillStyle = lit;
        const my = sy + sh * 0.3;
        const heights = state === 'talk' ? [0.1, 0.22, 0.14, 0.24, 0.1] : [0.05, 0.05, 0.05, 0.05, 0.05];
        heights.forEach((height, index) => {
            const bar = r * height;
            ctx.beginPath();
            ctx.roundRect(c + (index - 2) * r * 0.2 - 4, my - bar / 2, 8, bar, 3);
            ctx.fill();
        });
    }

    return canvas;
}

const ORANGE_BODY = '#f26f2a';

/**
 * The soft clay look: a matte, rounded, brightly coloured blob with two dark
 * pill eyes and no outline, arms or mouth - lit from the top left. The
 * manager wears a graduation cap. `state` blinks (thin eyes) or talks
 * (slightly taller eyes).
 */
function clayTexture(shape, color, state, isManager) {
    const size = 256;
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = size;
    const ctx = canvas.getContext('2d');
    const c = size / 2;
    const r = 80;
    const cy = c - 6;

    ctx.save();
    ctx.translate(c, 236);
    ctx.scale(1, 0.16);
    const ground = ctx.createRadialGradient(0, 0, 0, 0, 0, 78);
    ground.addColorStop(0, 'rgba(0,0,0,0.38)');
    ground.addColorStop(1, 'rgba(0,0,0,0)');
    ctx.fillStyle = ground;
    ctx.beginPath();
    ctx.arc(0, 0, 78, 0, 2 * Math.PI);
    ctx.fill();
    ctx.restore();

    shapePath(ctx, shape, c, cy, r);
    const body = ctx.createRadialGradient(c - r * 0.38, cy - r * 0.45, r * 0.05, c - r * 0.1, cy - r * 0.1, r * 1.4);
    body.addColorStop(0, mix(color, '#ffffff', 0.2));
    body.addColorStop(0.55, color);
    body.addColorStop(1, mix(color, '#000000', 0.16));
    ctx.fillStyle = body;
    ctx.fill();

    // Volume: a soft dark rim on the lower right, a soft light rim on the
    // upper left (shadows cast inward by the area outside the shape), a
    // broad glossy light, a small specular dot and light bounced from below.
    // The area outside the silhouette as an image, so shapes made of several
    // overlapping circles (cloud, clover) get one clean edge.
    const outside = document.createElement('canvas');
    outside.width = outside.height = size;
    const outsideCtx = outside.getContext('2d');
    outsideCtx.fillRect(0, 0, size, size);
    outsideCtx.globalCompositeOperation = 'destination-out';
    shapePath(outsideCtx, shape, c, cy, r);
    outsideCtx.fill();

    const rim = (shadowColor, blur, dx, dy) => {
        ctx.save();
        shapePath(ctx, shape, c, cy, r);
        ctx.clip();
        // Drawn far off canvas: only its shadow lands on the avatar.
        ctx.shadowColor = shadowColor;
        ctx.shadowBlur = blur;
        ctx.shadowOffsetX = dx + size * 2;
        ctx.shadowOffsetY = dy;
        ctx.drawImage(outside, -size * 2, 0);
        ctx.restore();
    };
    rim(`${mix(color, '#000000', 0.5)}cc`, 22, -6, -12);
    rim('rgba(255,255,255,0.55)', 16, 5, 9);

    const glow = (x, y, rx, ry, rotation, rgb, alpha) => {
        ctx.save();
        shapePath(ctx, shape, c, cy, r);
        ctx.clip();
        ctx.translate(x, y);
        ctx.rotate(rotation);
        ctx.scale(1, ry / rx);
        const gradient = ctx.createRadialGradient(0, 0, 0, 0, 0, rx);
        gradient.addColorStop(0, `rgba(${rgb},${alpha})`);
        gradient.addColorStop(1, `rgba(${rgb},0)`);
        ctx.fillStyle = gradient;
        ctx.beginPath();
        ctx.arc(0, 0, rx, 0, 2 * Math.PI);
        ctx.fill();
        ctx.restore();
    };
    const bounce = new THREE.Color(mix(color, '#ffffff', 0.45));
    glow(c - r * 0.3, cy - r * 0.5, r * 0.62, r * 0.36, -0.5, '255,255,255', 0.5);
    glow(c - r * 0.42, cy - r * 0.6, r * 0.2, r * 0.1, -0.5, '255,255,255', 0.75);
    glow(c + r * 0.12, cy + r * 1.0, r * 0.8, r * 0.3, 0, `${Math.round(bounce.r * 255)},${Math.round(bounce.g * 255)},${Math.round(bounce.b * 255)}`, 0.4);

    const eyeOffset = { triangle: 0.24, teardrop: 0.14, cloud: 0.05, pentagon: 0.04 }[shape] ?? -0.08;
    const eyeY = cy + r * eyeOffset;
    const tilt = (color === ORANGE_BODY ? 16 : shape === 'pentagon' ? -14 : 0) * (Math.PI / 180);
    const width = r * 0.2;
    const height = state === 'blink' ? 6 : r * (state === 'talk' ? 0.54 : 0.48);

    ctx.fillStyle = '#1c1626';
    [-1, 1].forEach((side) => {
        ctx.save();
        ctx.translate(c + side * r * 0.26, eyeY);
        ctx.rotate(tilt * side);
        ctx.beginPath();
        ctx.roundRect(-width / 2, -height / 2, width, height, width / 2);
        ctx.fill();
        ctx.restore();
    });

    if (isManager) {
        const top = cy - r * 0.86;
        ctx.fillStyle = '#2a2438';
        ctx.beginPath();
        ctx.roundRect(c - r * 0.34, top, r * 0.68, r * 0.34, r * 0.1);
        ctx.fill();
        ctx.fillStyle = '#3b3350';
        ctx.beginPath();
        ctx.moveTo(c, top - r * 0.38);
        ctx.lineTo(c + r * 0.72, top - r * 0.08);
        ctx.lineTo(c, top + r * 0.2);
        ctx.lineTo(c - r * 0.72, top - r * 0.08);
        ctx.closePath();
        ctx.fill();
        ctx.strokeStyle = '#fbbf24';
        ctx.lineWidth = 4;
        ctx.lineCap = 'round';
        ctx.beginPath();
        ctx.moveTo(c + r * 0.5, top - r * 0.04);
        ctx.lineTo(c + r * 0.5, top + r * 0.34);
        ctx.stroke();
        ctx.fillStyle = '#fbbf24';
        ctx.beginPath();
        ctx.arc(c + r * 0.5, top + r * 0.38, 6, 0, 2 * Math.PI);
        ctx.fill();
    }

    return canvas;
}

/** The sprite texture for one avatar frame; `style` is 'robot' or the default clay look. */
function avatarTexture(shape, color, state, isManager, style, pose) {
    const key = `${style}|${shape}|${color}|${state}|${style === 'robot' ? pose : 0}`;
    if (avatarCache.has(key)) {
        return avatarCache.get(key);
    }

    const canvas = style === 'robot' ? robotTexture(shape, color, state, isManager, pose) : clayTexture(shape, color, state, isManager);
    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    avatarCache.set(key, texture);

    return texture;
}

const bubbleCache = new Map();

/** A cloud-shaped speech bubble holding the assistant's last words (up to 3 lines). */
function bubbleTexture(text) {
    if (bubbleCache.has(text)) {
        return bubbleCache.get(text);
    }

    const width = 384;
    const height = 192;
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');

    const bumps = [];
    const left = 40;
    const right = width - 40;
    const top = 30;
    const bottom = 128;
    for (let x = left; x <= right; x += 44) {
        bumps.push([x, top, 26], [x, bottom, 26]);
    }
    for (let y = top + 36; y < bottom - 20; y += 36) {
        bumps.push([left, y, 26], [right, y, 26]);
    }
    const tail = [[44, 158, 12], [22, 178, 7]];

    const draw = (stroke) => {
        ctx.beginPath();
        ctx.roundRect(left, top, right - left, bottom - top, 30);
        [...bumps, ...tail].forEach(([x, y, radius]) => {
            ctx.moveTo(x + radius, y);
            ctx.arc(x, y, radius, 0, 2 * Math.PI);
        });
        if (stroke) {
            ctx.lineWidth = 8;
            ctx.strokeStyle = INK;
            ctx.stroke();
        } else {
            ctx.fillStyle = '#f8fafc';
            ctx.fill();
        }
    };
    draw(true);
    draw(false);

    ctx.fillStyle = INK;
    ctx.font = '600 24px Vazirmatn, sans-serif';
    ctx.direction = 'rtl';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';

    const maxWidth = right - left - 24;
    const lines = [];
    let current = '';
    text.split(' ').forEach((word) => {
        const candidate = current ? `${current} ${word}` : word;
        if (ctx.measureText(candidate).width > maxWidth && current) {
            lines.push(current);
            current = word;
        } else {
            current = candidate;
        }
    });
    lines.push(current);

    const shown = lines.slice(0, 3);
    if (lines.length > 3) {
        shown[2] = `${shown[2]}…`;
    }
    const lineHeight = 31;
    const startY = (top + bottom) / 2 - ((shown.length - 1) * lineHeight) / 2;
    shown.forEach((line, index) => ctx.fillText(line, width / 2, startY + index * lineHeight, maxWidth));

    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    bubbleCache.set(text, texture);

    return texture;
}

/** Keeps a node's live ring and speech bubble in step with fresh data (its 3D object is built once). */
function applyNodeState(group, node, bubblesOn) {
    const data = group.userData;
    data.live = node.live;
    data.halo.material.opacity = node.live ? 1 : 0;

    if (node.role_label) {
        if (data.roleText !== node.role_label) {
            if (data.roleBadge) {
                group.remove(data.roleBadge);
            }
            const badge = new SpriteText(node.role_label, 3.4, INK);
            badge.fontFace = 'Vazirmatn, sans-serif';
            badge.fontWeight = '700';
            badge.backgroundColor = ROLE_COLORS[node.role] ?? '#94a3b8';
            badge.padding = [2.2, 1];
            badge.borderRadius = 3;
            badge.material.depthWrite = false;
            badge.position.y = data.labelY - 8.5;
            group.add(badge);
            data.roleBadge = badge;
            data.roleText = node.role_label;
        }
    } else if (data.roleBadge) {
        group.remove(data.roleBadge);
        data.roleBadge = null;
        data.roleText = null;
    }

    const text = bubblesOn && node.reply ? node.reply.text : null;
    if (!text) {
        if (data.bubble) {
            data.bubble.visible = false;
        }

        return;
    }

    if (!data.bubble) {
        const bubble = new THREE.Sprite(new THREE.SpriteMaterial({ transparent: true, depthTest: false, depthWrite: false }));
        bubble.renderOrder = 10;
        bubble.center.set(0, 0);
        bubble.position.set(data.radius * 1.2, data.radius * 1.5, 0);
        bubble.scale.set(data.radius * 7, data.radius * 3.5, 1);
        group.add(bubble);
        data.bubble = bubble;
    }
    data.bubble.visible = true;
    if (data.bubbleText !== text) {
        data.bubbleText = text;
        data.bubble.material.map = bubbleTexture(text);
        data.bubble.material.needsUpdate = true;
    }
}

function buildNode(node, ringMap, style, bubblesOn) {
    const group = new THREE.Group();
    const color = new THREE.Color(node.color);
    const isManager = node.id === 'manager' || node.hub === true;
    const radius = isManager ? 13 : 10;
    const base = radius * 4.6;

    const core = new THREE.Sprite(new THREE.SpriteMaterial({ map: avatarTexture(node.shape, node.color, 'idle', isManager, style, 0), transparent: true, depthWrite: false }));
    core.scale.set(base, base, 1);
    const halo = new THREE.Sprite(new THREE.SpriteMaterial({ map: ringMap, color, transparent: true, opacity: node.live ? 1 : 0, depthWrite: false }));
    halo.scale.set(base * 1.0, base * 1.0, 1);

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
    label.position.y = -(radius * 2.45 + 4);
    const labelY = label.position.y;
    group.add(label);

    group.userData = { ...group.userData, core, halo, base, radius, labelY, live: node.live, shape: node.shape, color: node.color, isManager };
    applyNodeState(group, node, bubblesOn);

    return group;
}

export function mount(element, { onSelect, initialStyle = 'cartoon', initialBubbles = true }) {
    const ringMap = ringTexture();
    const motion = !reducedMotion();
    let spinning = motion;
    let autoFit = true;
    let style = initialStyle;
    let bubblesOn = initialBubbles;
    let viewKey = 0;

    const graph = ForceGraph3D({ controlType: 'orbit' })(element)
        .backgroundColor('rgba(0,0,0,0)')
        .showNavInfo(false)
        .nodeLabel(() => '')
        .nodeThreeObject((node) => buildNode(node, ringMap, style, bubblesOn))
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
    graph.d3Force('charge').strength(-520);
    graph.d3Force('link').distance(130);

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
                const now = performance.now() / 1000;
                const blinking = (now + node.index * 1.7) % 4.2 < 0.14;
                const talking = data.live && Math.floor(now * 3 + node.index) % 2 === 0;
                const state = blinking ? 'blink' : talking ? 'talk' : 'idle';
                const pose = data.live ? Math.floor(now * 2 + node.index) % 2 : 0;
                if (data.state !== state || data.pose !== pose) {
                    data.state = state;
                    data.pose = pose;
                    data.core.material.map = avatarTexture(data.shape, data.color, state, data.isManager, style, pose);
                }
                data.core.position.y = data.live ? Math.abs(Math.sin(now * 4 + node.index)) * 4 : Math.sin(now * 1.4 + node.index * 2) * 1.6;
                data.core.material.rotation = Math.sin(now * 1.3 + node.index * 1.9) * (data.live ? 0.09 : 0.04);
                if (data.live) {
                    const scale = data.base * (0.98 + 0.05 * Math.sin(tick * 1.3 + node.index));
                    data.halo.scale.set(scale, scale, 1);
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
            nodes: data.nodes.map((node) => Object.assign(previous.get(node.id) ?? {}, node, node.id === 'manager' || node.hub ? { fx: 0, fy: 0, fz: 0 } : { fx: null, fy: null, fz: null })),
            links: data.edges.map((edge) => ({ ...edge })),
        };
    };

    graph.graphData(toGraph({ nodes: [], edges: [] }));

    return {
        update(data) {
            // A different mission (or back to everyone) rebuilds every figure: roles, hub and sizes change.
            const key = data.mission ? data.mission.id : 0;
            if (key !== viewKey) {
                viewKey = key;
                graph.graphData({ nodes: [], links: [] });
                autoFit = true;
            }
            graph.graphData(toGraph(data));
            graph.graphData().nodes.forEach((node) => node.__threeObj && applyNodeState(node.__threeObj, node, bubblesOn));
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
        refit() {
            autoFit = true;
            [700, 2200].forEach((delay) => setTimeout(() => autoFit && graph.zoomToFit(600, 120), delay));
        },
        setBubbles(on) {
            bubblesOn = on;
            graph.graphData().nodes.forEach((node) => node.__threeObj && applyNodeState(node.__threeObj, node, bubblesOn));
        },
        setStyle(next) {
            style = next;
            graph.graphData().nodes.forEach((node) => {
                const data = node.__threeObj?.userData;
                if (data) {
                    data.state = 'idle';
                    data.core.material.map = avatarTexture(data.shape, data.color, 'idle', data.isManager, style, 0);
                }
            });
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
