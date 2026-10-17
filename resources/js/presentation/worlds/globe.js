/*
 * The planet, used twice: as the cover hero and as the orbit-to-polygon
 * descent at the top of the mapping section (the page's signature move).
 *
 * Earth textures: Solar System Scope (solarsystemscope.com/textures), CC BY 4.0,
 * as resized and merged in the three.js examples. Stored locally under
 * public/vendor/earth so the deck keeps working offline.
 */

const DEG = Math.PI / 180;
const clamp01 = (value) => Math.min(1, Math.max(0, value));
const range = (value, start, end) => clamp01((value - start) / (end - start));
const easeOut = (t) => 1 - ((1 - t) ** 3);
const easeInOut = (t) => (t < 0.5 ? 4 * t * t * t : 1 - (((-2 * t) + 2) ** 3) / 2);
const lerp = (from, to, t) => from + ((to - from) * t);

const AREA6_CENTER = { lat: -7.72, lon: 111.47 };

const assetBase = () => {
    const worker = window.__PRESENTATION_CONFIG__?.serviceWorkerUrl;
    if (worker) return String(worker).replace(/presentation-sw\.js(?:\?.*)?$/, '');
    return `${window.location.origin}/`;
};

/** Same mapping three.js SphereGeometry uses for its UVs (u = 0 at -180 degrees). */
export function latLonToVector(THREE, lat, lon, radius = 1, target = new THREE.Vector3()) {
    const phi = (lon + 180) * DEG;
    const theta = (90 - lat) * DEG;
    return target.set(-radius * Math.cos(phi) * Math.sin(theta), radius * Math.cos(theta), radius * Math.sin(phi) * Math.sin(theta));
}

function vectorToLatLon(point) {
    const length = point.length() || 1;
    const theta = Math.acos(Math.min(1, Math.max(-1, point.y / length)));
    let lon = (Math.atan2(point.z, -point.x) / DEG) - 180;
    if (lon < -180) lon += 360;
    return { lat: 90 - (theta / DEG), lon };
}

function loadTexture(THREE, file, { color = true } = {}) {
    const texture = new THREE.TextureLoader().load(`${assetBase()}vendor/earth/${file}`);
    texture.colorSpace = color ? THREE.SRGBColorSpace : THREE.NoColorSpace;
    texture.anisotropy = 8;
    return texture;
}

/* ------------------------------------------------------------ materials -- */

function earthMaterial(THREE) {
    return new THREE.ShaderMaterial({
        uniforms: {
            uDay: { value: loadTexture(THREE, 'earth_day_4096.jpg') },
            uNight: { value: loadTexture(THREE, 'earth_night_4096.jpg') },
            uSurface: { value: loadTexture(THREE, 'earth_bump_roughness_clouds_4096.jpg', { color: false }) },
            uSun: { value: new THREE.Vector3(-0.5, 0.35, 0.8).normalize() },
            uFocus: { value: 0 },
            uCloudShadow: { value: 1 },
            uTexel: { value: new THREE.Vector2(1 / 4096, 1 / 2048) },
            uAtmosphere: { value: new THREE.Color(0x4f8dff) },
            uGrid: { value: 0 },
        },
        vertexShader: /* glsl */ `
            varying vec2 vUv;
            varying vec3 vNormalW;
            varying vec3 vPositionW;
            void main() {
                vUv = uv;
                vec4 world = modelMatrix * vec4(position, 1.0);
                vPositionW = world.xyz;
                vNormalW = normalize(mat3(modelMatrix) * normal);
                gl_Position = projectionMatrix * viewMatrix * world;
            }
        `,
        fragmentShader: /* glsl */ `
            uniform sampler2D uDay;
            uniform sampler2D uNight;
            uniform sampler2D uSurface;
            uniform vec3 uSun;
            uniform float uFocus;
            uniform float uCloudShadow;
            uniform vec2 uTexel;
            uniform vec3 uAtmosphere;
            uniform float uGrid;
            varying vec2 vUv;
            varying vec3 vNormalW;
            varying vec3 vPositionW;

            // Quarter-degree graticule: crisp structure where the texture runs out of detail.
            float graticule(vec2 uv) {
                vec2 degrees = vec2(uv.x * 360.0 - 180.0, uv.y * 180.0 - 90.0) / 0.25;
                vec2 cell = abs(fract(degrees - 0.5) - 0.5) / max(fwidth(degrees), vec2(1e-5));
                return 1.0 - min(min(cell.x, cell.y), 1.0);
            }

            void main() {
                vec3 base = normalize(vNormalW);
                vec3 view = normalize(cameraPosition - vPositionW);
                vec3 east = normalize(cross(vec3(0.0, 1.0, 0.0), base) + vec3(1e-5));
                vec3 north = cross(base, east);

                vec4 surface = texture2D(uSurface, vUv);
                float hx = texture2D(uSurface, vUv + vec2(uTexel.x, 0.0)).r - surface.r;
                float hy = texture2D(uSurface, vUv + vec2(0.0, uTexel.y)).r - surface.r;
                vec3 normal = normalize(base - (east * hx + north * hy) * 5.0);

                float light = dot(normal, uSun);
                float daylight = smoothstep(-0.2, 0.32, dot(base, uSun));
                vec3 day = texture2D(uDay, vUv).rgb;
                vec3 night = texture2D(uNight, vUv).rgb;

                // Clouds cast a soft shadow a little east of where they float.
                float shadow = smoothstep(0.25, 1.0, texture2D(uSurface, vUv + vec2(0.0012, -0.0006)).b) * uCloudShadow;

                vec3 color = day * (0.03 + 1.25 * max(light, 0.0)) * (1.0 - shadow * 0.42);

                // Oceans are glossy, land is matte: the roughness channel drives the glint.
                vec3 halfway = normalize(uSun + view);
                float gloss = 1.0 - surface.g;
                float glint = pow(max(dot(base, halfway), 0.0), 600.0) * gloss * daylight;
                color += vec3(1.0, 0.86, 0.66) * glint * 0.3;

                // City lights on the night side.
                color += night * vec3(1.0, 0.78, 0.48) * pow(1.0 - daylight, 1.6) * 2.4;

                // Atmospheric scattering thickens toward the limb.
                float limb = pow(1.0 - max(dot(base, view), 0.0), 2.6);
                color = mix(color, uAtmosphere * (0.18 + daylight * 0.9), limb * 0.72);

                // Descent focus: the planet quiets so the polygons own the light.
                float luminance = dot(color, vec3(0.299, 0.587, 0.114));
                color = mix(color, vec3(luminance) * vec3(0.42, 0.56, 0.86), uFocus * 0.72);
                color *= 1.0 - uFocus * 0.5;
                color += vec3(0.32, 0.58, 1.0) * graticule(vUv) * 0.16 * uGrid;

                gl_FragColor = vec4(color, 1.0);
                #include <tonemapping_fragment>
                #include <colorspace_fragment>
            }
        `,
    });
}

function cloudMaterial(THREE, surface) {
    return new THREE.ShaderMaterial({
        transparent: true,
        depthWrite: false,
        uniforms: {
            uSurface: { value: surface },
            uSun: { value: new THREE.Vector3(-0.5, 0.35, 0.8).normalize() },
            uOpacity: { value: 1 },
        },
        vertexShader: /* glsl */ `
            varying vec2 vUv;
            varying vec3 vNormalW;
            void main() {
                vUv = uv;
                vNormalW = normalize(mat3(modelMatrix) * normal);
                gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
            }
        `,
        fragmentShader: /* glsl */ `
            uniform sampler2D uSurface;
            uniform vec3 uSun;
            uniform float uOpacity;
            varying vec2 vUv;
            varying vec3 vNormalW;
            void main() {
                float cover = smoothstep(0.22, 1.0, texture2D(uSurface, vUv).b);
                float light = max(dot(normalize(vNormalW), uSun), 0.0);
                vec3 color = vec3(0.92, 0.95, 1.0) * (0.05 + light * 1.25);
                gl_FragColor = vec4(color, cover * 0.92 * uOpacity);
                #include <tonemapping_fragment>
                #include <colorspace_fragment>
            }
        `,
    });
}

function atmosphereMaterial(THREE) {
    return new THREE.ShaderMaterial({
        transparent: true,
        depthWrite: false,
        side: THREE.BackSide,
        blending: THREE.AdditiveBlending,
        uniforms: {
            uSun: { value: new THREE.Vector3(-0.5, 0.35, 0.8).normalize() },
            uOpacity: { value: 1 },
        },
        vertexShader: /* glsl */ `
            varying vec3 vNormalV;
            varying vec3 vNormalW;
            void main() {
                vNormalV = normalize(normalMatrix * normal);
                vNormalW = normalize(mat3(modelMatrix) * normal);
                gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
            }
        `,
        fragmentShader: /* glsl */ `
            uniform vec3 uSun;
            uniform float uOpacity;
            varying vec3 vNormalV;
            varying vec3 vNormalW;
            void main() {
                // Back faces of a larger shell: brightest against the planet's limb, fading into space.
                float facing = -dot(vNormalV, vec3(0.0, 0.0, 1.0));
                float rim = pow(clamp(facing, 0.0, 1.0), 1.6) * smoothstep(0.0, 0.18, facing);
                float lit = smoothstep(-0.5, 0.5, dot(-vNormalW, uSun)) * 0.85 + 0.15;
                vec3 glow = vec3(0.3, 0.56, 1.0) * rim * lit * 1.6 * uOpacity;
                gl_FragColor = vec4(glow, clamp(max(glow.r, max(glow.g, glow.b)), 0.0, 1.0));
            }
        `,
    });
}

function starField(THREE, count = 2600) {
    const positions = new Float32Array(count * 3);
    const colors = new Float32Array(count * 3);
    const tint = new THREE.Color();
    for (let index = 0; index < count; index += 1) {
        const direction = new THREE.Vector3(Math.random() * 2 - 1, Math.random() * 2 - 1, Math.random() * 2 - 1).normalize();
        direction.multiplyScalar(40 + Math.random() * 25).toArray(positions, index * 3);
        tint.setHSL(0.58 + (Math.random() * 0.1), 0.35, 0.55 + (Math.random() * 0.4)).toArray(colors, index * 3);
    }
    const geometry = new THREE.BufferGeometry();
    geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
    geometry.setAttribute('color', new THREE.BufferAttribute(colors, 3));
    const material = new THREE.PointsMaterial({ size: 1.6, sizeAttenuation: false, vertexColors: true, transparent: true, opacity: 0.85, depthWrite: false });
    return new THREE.Points(geometry, material);
}

/** The planet as one group: surface, a cloud deck that floats above it, and the atmosphere shell. */
function planet(THREE, segments = 160) {
    const group = new THREE.Group();
    const surface = earthMaterial(THREE);
    const earth = new THREE.Mesh(new THREE.SphereGeometry(1, segments, Math.round(segments / 2)), surface);
    const clouds = new THREE.Mesh(new THREE.SphereGeometry(1.0075, segments, Math.round(segments / 2)), cloudMaterial(THREE, surface.uniforms.uSurface.value));
    const atmosphere = new THREE.Mesh(new THREE.SphereGeometry(1.085, 96, 48), atmosphereMaterial(THREE));
    group.add(earth, clouds, atmosphere);
    const setSun = (direction) => {
        surface.uniforms.uSun.value.copy(direction);
        clouds.material.uniforms.uSun.value.copy(direction);
        atmosphere.material.uniforms.uSun.value.copy(direction);
    };
    return { group, earth, clouds, atmosphere, surface, setSun };
}

/** A pulse ring and a radial beam marking a place on the surface. */
function beacon(THREE, lat, lon) {
    const group = new THREE.Group();
    const normal = latLonToVector(THREE, lat, lon, 1);
    group.position.copy(normal);
    group.quaternion.setFromUnitVectors(new THREE.Vector3(0, 1, 0), normal.clone().normalize());
    const ringMaterial = new THREE.MeshBasicMaterial({ color: 0xff5a5f, transparent: true, depthWrite: false, side: THREE.DoubleSide, blending: THREE.AdditiveBlending });
    const rings = [0, 1].map(() => {
        const ring = new THREE.Mesh(new THREE.RingGeometry(0.85, 1, 64), ringMaterial.clone());
        ring.rotation.x = -Math.PI / 2;
        ring.position.y = 0.002;
        group.add(ring);
        return ring;
    });
    const beamMaterial = new THREE.ShaderMaterial({
        transparent: true,
        depthWrite: false,
        blending: THREE.AdditiveBlending,
        uniforms: { uOpacity: { value: 1 } },
        vertexShader: 'varying float vY; void main(){ vY = uv.y; gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0); }',
        fragmentShader: 'uniform float uOpacity; varying float vY; void main(){ float a = pow(1.0 - vY, 2.2) * uOpacity; gl_FragColor = vec4(vec3(1.0, 0.42, 0.42) * a, a); }',
    });
    const beam = new THREE.Mesh(new THREE.CylinderGeometry(0.0035, 0.0035, 0.34, 12, 1, true), beamMaterial);
    beam.position.y = 0.17;
    const core = new THREE.Mesh(new THREE.SphereGeometry(0.009, 16, 12), new THREE.MeshBasicMaterial({ color: 0xffd1d1 }));
    group.add(beam, core);
    return {
        group,
        update(time, opacity = 1) {
            rings.forEach((ring, index) => {
                const phase = ((time * 0.45) + (index * 0.5)) % 1;
                const size = 0.012 + (phase * 0.07);
                ring.scale.setScalar(size);
                ring.material.opacity = (1 - phase) * 0.9 * opacity;
            });
            beamMaterial.uniforms.uOpacity.value = opacity;
            core.material.opacity = opacity;
            core.visible = opacity > 0.02;
        },
    };
}

/* ------------------------------------------------------------ the cover -- */

export function globeWorld(stage, data) {
    const { THREE, scene, camera } = stage;
    const center = data?.mapping?.center || AREA6_CENTER;
    const world = planet(THREE, 180);
    const tilt = new THREE.Group();
    tilt.add(world.group);
    scene.add(tilt);
    const stars = starField(THREE);
    scene.add(stars);
    const marker = beacon(THREE, center.lat, center.lon);
    world.group.add(marker.group);
    const markerLabel = stage.label('pres-world__label--beacon', `<b>${escape(data?.scopeLabel || 'Area 6')}</b><span>Madiun, Magetan, Ngawi, Ponorogo</span>`);
    const sun = new THREE.Vector3(-0.86, 0.28, 0.46).normalize();
    world.setSun(sun);

    const facing = -(center.lon + 90) * DEG;
    camera.fov = 28;
    camera.position.set(0, 0, 4.4);
    camera.lookAt(0, 0, 0);
    const markerWorld = new THREE.Vector3();
    const toCamera = new THREE.Vector3();

    return {
        resize(width, height) {
            const wide = width / height > 1.15;
            stage.shift = wide ? { x: 0.28, y: 0.07 } : { x: 0, y: 0 };
            camera.position.z = wide ? 4.9 : 4.6;
        },
        setData(next) {
            markerLabel.innerHTML = `<b>${escape(next?.scopeLabel || 'Area 6')}</b><span>Madiun, Magetan, Ngawi, Ponorogo</span>`;
        },
        update(progress, time, pointer, still) {
            const leave = easeInOut(range(progress, 0.5, 1));
            const drift = still ? 0 : Math.sin(time * 0.05) * 3 * DEG;
            world.group.rotation.y = facing + drift - (leave * 26 * DEG);
            tilt.rotation.x = (center.lat * DEG * 0.9) + (pointer.y * 2.5 * DEG) - (leave * 6 * DEG);
            tilt.rotation.y = pointer.x * 4 * DEG;
            tilt.position.y = leave * 0.55;
            world.clouds.rotation.y = still ? 0 : time * 0.006;
            stars.rotation.y = (pointer.x * 1.2 * DEG) + (leave * 4 * DEG);
            stars.rotation.x = pointer.y * 0.8 * DEG;
            marker.update(still ? 0.3 : time, 1 - range(progress, 0.62, 0.8));
        },
        afterRender(progress) {
            marker.group.getWorldPosition(markerWorld);
            toCamera.copy(camera.position).sub(markerWorld);
            const facingCamera = markerWorld.clone().normalize().dot(toCamera.normalize()) > 0.15;
            markerWorld.addScaledVector(markerWorld.clone().normalize(), 0.36);
            stage.place(markerLabel, markerWorld, { hide: !facingCamera, opacity: 1 - range(progress, 0.6, 0.75) });
        },
    };
}

/* ------------------------------------------------------- the descent -- */

// Absolute 0 to 100% scale with more stops where Area 6 actually sits (about 30 to 80%).
// The legend in the orbit copy uses the same stops.
const PENETRATION_STOPS = [
    [0, [0.1, 0.13, 0.3]],
    [30, [0.2, 0.22, 0.62]],
    [45, [0.04, 0.34, 0.86]],
    [55, [0.1, 0.56, 1.0]],
    [62, [0.13, 0.78, 0.92]],
    [70, [0.36, 0.9, 0.74]],
    [80, [0.78, 0.97, 0.66]],
    [100, [1.0, 0.98, 0.86]],
];

export function penetrationColor(THREE, value) {
    const numeric = Math.min(100, Math.max(0, Number(value) || 0));
    for (let index = 1; index < PENETRATION_STOPS.length; index += 1) {
        const [stop, color] = PENETRATION_STOPS[index];
        if (numeric <= stop) {
            const [previousStop, previous] = PENETRATION_STOPS[index - 1];
            const t = (numeric - previousStop) / (stop - previousStop);
            return new THREE.Color(lerp(previous[0], color[0], t), lerp(previous[1], color[1], t), lerp(previous[2], color[2], t));
        }
    }
    return new THREE.Color(...PENETRATION_STOPS.at(-1)[1]);
}

const polygonsOf = (geometry) => {
    if (!geometry) return [];
    if (geometry.type === 'Polygon') return [geometry.coordinates];
    if (geometry.type === 'MultiPolygon') return geometry.coordinates;
    return [];
};

const openRing = (ring) => {
    const points = ring.map(([lon, lat]) => [Number(lon), Number(lat)]).filter(([lon, lat]) => Number.isFinite(lon) && Number.isFinite(lat));
    if (points.length > 2) {
        const [firstLon, firstLat] = points[0];
        const [lastLon, lastLat] = points.at(-1);
        if (firstLon === lastLon && firstLat === lastLat) points.pop();
    }
    return points;
};

const signedArea = (points) => points.reduce((sum, [x1, y1], index) => {
    const [x2, y2] = points[(index + 1) % points.length];
    return sum + ((x1 * y2) - (x2 * y1));
}, 0) / 2;

/**
 * Every kecamatan as a prism standing on the sphere. Vertices sit on the unit
 * sphere; the vertex shader raises the top cap by penetration as uRise grows,
 * so all 76 prisms are one draw call and rise in a staggered wave.
 */
function prismField(THREE, districts) {
    const positions = [];
    const normals = [];
    const colors = [];
    const tops = [];
    const heights = [];
    const delays = [];
    const outline = { positions: [], heights: [], delays: [] };
    const scratch = { a: new THREE.Vector3(), b: new THREE.Vector3(), c: new THREE.Vector3(), n: new THREE.Vector3(), e: new THREE.Vector3() };

    const pushVertex = (point, normal, color, top, height, delay) => {
        positions.push(point.x, point.y, point.z);
        normals.push(normal.x, normal.y, normal.z);
        colors.push(color.r, color.g, color.b);
        tops.push(top);
        heights.push(height);
        delays.push(delay);
    };

    districts.forEach((district) => {
        const color = district.color;
        const side = color.clone().multiplyScalar(0.62);
        polygonsOf(district.geometry).forEach((rings) => {
            const contour = openRing(rings[0] || []);
            if (contour.length < 3) return;
            const holes = rings.slice(1).map(openRing).filter((ring) => ring.length > 2);
            const triangles = THREE.ShapeUtils.triangulateShape(contour.map(([x, y]) => new THREE.Vector2(x, y)), holes.map((ring) => ring.map(([x, y]) => new THREE.Vector2(x, y))));
            const flat = [...contour, ...holes.flat()];
            triangles.forEach((triangle) => {
                const corners = triangle.map((index) => flat[index]);
                if (signedArea(corners) < 0) corners.reverse();
                corners.forEach(([lon, lat]) => {
                    const point = latLonToVector(THREE, lat, lon, 1);
                    pushVertex(point, point, color, 1, district.height, district.delay);
                });
            });

            const centroid = latLonToVector(THREE, district.center.lat, district.center.lon, 1);
            [contour, ...holes].forEach((ring) => {
                ring.forEach(([lon, lat], index) => {
                    const [nextLon, nextLat] = ring[(index + 1) % ring.length];
                    const a = latLonToVector(THREE, lat, lon, 1, scratch.a);
                    const b = latLonToVector(THREE, nextLat, nextLon, 1, scratch.b);
                    const middle = scratch.c.copy(a).add(b).multiplyScalar(0.5);
                    const normal = scratch.n.copy(b).sub(a).cross(middle).normalize();
                    if (normal.dot(scratch.e.copy(middle).sub(centroid)) < 0) normal.negate();
                    // Quad a-b on the ground, a-b raised, wound to face outward.
                    const quad = [[a, 0], [b, 0], [b, 1], [a, 0], [b, 1], [a, 1]];
                    const edge = scratch.e.copy(b).sub(a);
                    const up = middle.clone().normalize();
                    const facing = edge.clone().cross(up).dot(normal) > 0;
                    const ordered = facing ? quad : [quad[0], quad[2], quad[1], quad[3], quad[5], quad[4]];
                    ordered.forEach(([point, top]) => pushVertex(point, normal, side, top, district.height, district.delay));
                    outline.positions.push(a.x, a.y, a.z, b.x, b.y, b.z);
                    outline.heights.push(district.height, district.height);
                    outline.delays.push(district.delay, district.delay);
                });
            });
        });
    });

    const geometry = new THREE.BufferGeometry();
    geometry.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
    geometry.setAttribute('normal', new THREE.Float32BufferAttribute(normals, 3));
    geometry.setAttribute('color', new THREE.Float32BufferAttribute(colors, 3));
    geometry.setAttribute('aTop', new THREE.Float32BufferAttribute(tops, 1));
    geometry.setAttribute('aH', new THREE.Float32BufferAttribute(heights, 1));
    geometry.setAttribute('aDelay', new THREE.Float32BufferAttribute(delays, 1));

    const rise = { value: 0 };
    const raise = (shader) => {
        shader.uniforms.uRise = rise;
        shader.vertexShader = shader.vertexShader
            .replace('#include <common>', '#include <common>\nattribute float aTop;\nattribute float aH;\nattribute float aDelay;\nuniform float uRise;')
            .replace('#include <begin_vertex>', `#include <begin_vertex>
                float lift = clamp((uRise - aDelay) / 0.4, 0.0, 1.0);
                lift = 1.0 - pow(1.0 - lift, 3.0);
                transformed = normalize(position) * (1.00006 + aTop * aH * lift);`);
    };
    const material = new THREE.MeshPhysicalMaterial({ vertexColors: true, roughness: 0.38, metalness: 0.05, clearcoat: 0.5, clearcoatRoughness: 0.2, emissive: 0x06122a, emissiveIntensity: 0.5 });
    material.onBeforeCompile = raise;
    const mesh = new THREE.Mesh(geometry, material);

    const lineGeometry = new THREE.BufferGeometry();
    lineGeometry.setAttribute('position', new THREE.Float32BufferAttribute(outline.positions, 3));
    lineGeometry.setAttribute('aTop', new THREE.Float32BufferAttribute(new Array(outline.heights.length).fill(1), 1));
    lineGeometry.setAttribute('aH', new THREE.Float32BufferAttribute(outline.heights, 1));
    lineGeometry.setAttribute('aDelay', new THREE.Float32BufferAttribute(outline.delays, 1));
    const lineMaterial = new THREE.LineBasicMaterial({ color: 0xdff0ff, transparent: true, opacity: 0, depthWrite: false });
    lineMaterial.onBeforeCompile = raise;
    const lines = new THREE.LineSegments(lineGeometry, lineMaterial);

    const groundGeometry = lineGeometry.clone();
    groundGeometry.setAttribute('aTop', new THREE.Float32BufferAttribute(new Array(outline.heights.length).fill(0), 1));
    const groundMaterial = new THREE.LineBasicMaterial({ color: 0x9cc8ff, transparent: true, opacity: 0, depthWrite: false });
    groundMaterial.onBeforeCompile = raise;
    const ground = new THREE.LineSegments(groundGeometry, groundMaterial);

    return { mesh, lines, ground, rise, lineMaterial, groundMaterial };
}

function pointInRing(lon, lat, ring) {
    let inside = false;
    for (let index = 0, previous = ring.length - 1; index < ring.length; previous = index, index += 1) {
        const [x1, y1] = ring[index];
        const [x2, y2] = ring[previous];
        if (((y1 > lat) !== (y2 > lat)) && (lon < ((x2 - x1) * (lat - y1)) / ((y2 - y1) || 1e-12) + x1)) inside = !inside;
    }
    return inside;
}

function districtAt(districts, lon, lat) {
    return districts.find((district) => {
        const { minLon, maxLon, minLat, maxLat } = district.bounds;
        if (lon < minLon || lon > maxLon || lat < minLat || lat > maxLat) return false;
        return polygonsOf(district.geometry).some((rings) => pointInRing(lon, lat, openRing(rings[0] || []))
            && !rings.slice(1).some((hole) => pointInRing(lon, lat, openRing(hole))));
    }) || null;
}

export function descentWorld(stage, data) {
    const { THREE, scene, camera, renderer } = stage;
    stage.maxPixelRatio = 1.6;
    renderer.shadowMap.enabled = false;
    const world = planet(THREE, 220);
    scene.add(world.group);
    const stars = starField(THREE, 1800);
    scene.add(stars);
    scene.add(new THREE.HemisphereLight(0xbfd8ff, 0x0a1428, 0.55));
    const sunLight = new THREE.DirectionalLight(0xfff0dc, 1.7);
    scene.add(sunLight);
    const fill = new THREE.DirectionalLight(0x7fb0ff, 0.7);
    scene.add(fill);

    let field = null;
    let districts = [];
    let regencyLabels = [];
    let center = AREA6_CENTER;
    const tip = stage.host.closest('[data-world-track]')?.querySelector('[data-orbit-tip]') || null;
    const centerPoint = new THREE.Vector3();
    const north = new THREE.Vector3();
    const east = new THREE.Vector3();
    const sun = new THREE.Vector3();

    const build = (next) => {
        if (field) {
            scene.remove(field.mesh, field.lines, field.ground);
            [field.mesh, field.lines, field.ground].forEach((object) => { object.geometry.dispose(); object.material.dispose(); });
            field = null;
        }
        stage.clearLabels();
        const mapping = next?.mapping || {};
        center = mapping.center || AREA6_CENTER;
        latLonToVector(THREE, center.lat, center.lon, 1, centerPoint);
        east.set(0, 1, 0).cross(centerPoint).normalize();
        north.copy(centerPoint).cross(east).normalize();
        sun.copy(centerPoint).multiplyScalar(0.72).addScaledVector(east, -0.55).addScaledVector(north, 0.42).normalize();
        world.setSun(sun);
        sunLight.position.copy(sun).multiplyScalar(10);
        fill.position.copy(centerPoint).multiplyScalar(4).addScaledVector(east, 3);

        const span = Math.max(0.5, mapping.span || 1.2);
        districts = (mapping.districts || []).map((district, index, all) => {
            const distance = Math.hypot(district.center.lon - center.lon, district.center.lat - center.lat);
            return {
                ...district,
                color: penetrationColor(THREE, district.penetration),
                height: (0.0006 + (0.0085 * Math.min(100, Math.max(0, district.penetration)) / 100)) * (span / 1.2),
                delay: Math.min(0.6, (distance / span) * 0.85 + ((index / Math.max(1, all.length)) * 0.08)),
            };
        });
        if (districts.length) {
            field = prismField(THREE, districts);
            scene.add(field.ground, field.mesh, field.lines);
        }
        regencyLabels = (mapping.regencies || []).map((regency) => ({
            ...regency,
            // A city sits inside its regency; its label hangs below the point so the two never stack.
            element: stage.label(`pres-world__label--regency${/^kota\s/i.test(regency.name) ? ' is-below' : ''}`, `<b>${escape(regency.name)}</b><span>Penetrasi ${escape(regency.penetrationLabel)}</span>`),
            position: latLonToVector(THREE, regency.center.lat, regency.center.lon, 1 + (0.0098 * (span / 1.2))),
        }));
        stage.spanScale = span / 1.2;
        // Hover rays meet the prisms near their average top, not the ground under them.
        sphere.radius = 1 + (0.0055 * stage.spanScale);
    };
    // Hover reads a kecamatan: ray onto the sphere, then point-in-polygon in lon/lat.
    const raycaster = new THREE.Raycaster();
    const sphere = new THREE.Sphere(new THREE.Vector3(), 1);
    build(data);

    const hit = new THREE.Vector3();
    let interactive = false;
    const onMove = (event) => {
        if (!interactive || !tip || !districts.length) return;
        const rect = stage.host.getBoundingClientRect();
        const ndc = new THREE.Vector2((((event.clientX - rect.left) / rect.width) * 2) - 1, -((((event.clientY - rect.top) / rect.height) * 2) - 1));
        raycaster.setFromCamera(ndc, camera);
        const point = raycaster.ray.intersectSphere(sphere, hit) ? vectorToLatLon(hit) : null;
        const found = point ? districtAt(districts, point.lon, point.lat) : null;
        if (!found) {
            tip.hidden = true;
            return;
        }
        tip.hidden = false;
        tip.innerHTML = `<b>${escape(found.name)}</b><span>${escape(found.regency)}</span><dl><div><dt>Penetrasi</dt><dd>${escape(found.penetrationLabel)}</dd></div><div><dt>Potensi</dt><dd>${escape(found.potentialLabel)}</dd></div><div><dt>Existing</dt><dd>${escape(found.existingLabel)}</dd></div></dl>`;
        tip.style.transform = `translate3d(${(event.clientX - rect.left + 18).toFixed(0)}px, ${(event.clientY - rect.top + 18).toFixed(0)}px, 0)`;
    };
    const onLeave = () => { if (tip) tip.hidden = true; };
    stage.host.addEventListener('pointermove', onMove, { passive: true });
    stage.host.addEventListener('pointerleave', onLeave);

    const cameraPoint = new THREE.Vector3();
    const target = new THREE.Vector3();
    const up = new THREE.Vector3();
    const worldUp = new THREE.Vector3(0, 1, 0);

    return {
        resize(width, height) {
            const wide = width / height > 1.15;
            stage.shift = wide ? { x: 0.16, y: 0.07 } : { x: 0, y: 0.16 };
            camera.fov = wide ? 30 : 38;
        },
        setData(next) {
            build(next);
        },
        update(progress, time, pointer, still) {
            const p = still ? 1 : progress;
            const turn = easeInOut(range(p, 0, 0.56));
            const dive = easeInOut(range(p, 0.1, 0.6));
            const lean = easeInOut(range(p, 0.56, 0.86));
            const settle = range(p, 0.86, 1);
            const scale = stage.spanScale || 1;

            // Orbit to the region: longitude turns under us while the radius falls geometrically.
            const lat = lerp(12, center.lat, turn) - (lean * 1.7 * scale) + (pointer.y * 0.08 * lean);
            const lon = lerp(center.lon - 46, center.lon, turn) + (settle * 0.22 * scale) + (pointer.x * 0.12 * lean);
            const far = 3.7;
            const near = 1 + (0.105 * scale);
            const radius = lerp(1 + ((far - 1) * ((near - 1) / (far - 1)) ** dive), 1 + (0.058 * scale), lean);
            latLonToVector(THREE, lat, lon, radius, cameraPoint);
            camera.position.copy(cameraPoint);
            target.set(0, 0, 0).lerp(centerPoint, easeOut(range(p, 0.18, 0.58)));
            up.copy(worldUp).lerp(north, easeOut(range(p, 0.3, 0.6))).lerp(centerPoint, lean).normalize();
            camera.up.copy(up);
            camera.lookAt(target);
            const altitude = radius - 1;
            camera.near = Math.max(0.0008, altitude * 0.12);
            camera.far = Math.max(4, radius + 2);
            camera.updateProjectionMatrix();

            world.surface.uniforms.uFocus.value = easeInOut(range(p, 0.26, 0.56));
            world.surface.uniforms.uGrid.value = range(p, 0.36, 0.6);
            world.surface.uniforms.uCloudShadow.value = 1 - range(p, 0.42, 0.56);
            // The cloud deck thickens, then the camera breaks through it.
            world.clouds.material.uniforms.uOpacity.value = (1 - range(p, 0.44, 0.56)) * (0.75 + range(p, 0.3, 0.44) * 0.25);
            world.clouds.visible = world.clouds.material.uniforms.uOpacity.value > 0.01;
            world.clouds.rotation.y = still ? 0 : time * 0.004;
            world.atmosphere.material.uniforms.uOpacity.value = 1 - range(p, 0.38, 0.5);
            world.atmosphere.visible = world.atmosphere.material.uniforms.uOpacity.value > 0.01;
            stars.visible = radius > 1.4;

            if (field) {
                field.rise.value = range(p, 0.5, 0.86) * 1.0;
                field.groundMaterial.opacity = range(p, 0.4, 0.56) * 0.55;
                field.lineMaterial.opacity = range(p, 0.6, 0.86) * 0.5;
            }
            interactive = p > 0.8;
            if (!interactive && tip) tip.hidden = true;
        },
        afterRender(progress) {
            const reveal = range(progress, 0.72, 0.84);
            regencyLabels.forEach((regency) => stage.place(regency.element, regency.position, { opacity: reveal, hide: reveal <= 0 }));
        },
        dispose() {
            stage.host.removeEventListener('pointermove', onMove);
            stage.host.removeEventListener('pointerleave', onLeave);
            if (tip) tip.hidden = true;
        },
    };
}

function escape(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));
}
