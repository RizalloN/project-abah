/* Local Solar System Scope / three.js Earth texture, CC BY 4.0. No remote tiles. */
const DEG = Math.PI / 180;
const smooth = (from, to, value) => {
    const t = Math.max(0, Math.min(1, (value - from) / (to - from)));
    return t * t * t * (t * (t * 6 - 15) + 10);
};
const lerp = (from, to, t) => from + (to - from) * t;

export function territoryFlight(stage, invalidate) {
    const { THREE, scene, host } = stage;
    const group = new THREE.Group();
    scene.add(group);
    let disposed = false;
    let ready = false;
    let failed = false;
    let radius = 300;
    const base = new URL(window.__PRESENTATION_CONFIG__?.serviceWorkerUrl || '/', `${window.location.origin}/`);
    const textures = [];
    const loaded = new Set();
    const loadTexture = (file, color = true) => {
        let texture;
        texture = new THREE.TextureLoader().load(new URL(`vendor/earth/${file}`, base).href, () => {
            if (disposed) { texture?.dispose(); return; }
            loaded.add(file);
            // The complete material arrives together, keeping the fallback intact while textures load.
            if (loaded.size === 3 && !failed) { ready = true; invalidate(); }
        }, undefined, () => {
            if (!disposed && !failed) { failed = true; invalidate(); }
        });
        texture.colorSpace = color ? THREE.SRGBColorSpace : THREE.NoColorSpace;
        texture.anisotropy = Math.min(8, stage.renderer.capabilities?.getMaxAnisotropy?.() || 1);
        textures.push(texture);
        return texture;
    };
    const day = loadTexture('earth_day_4096.jpg');
    const night = loadTexture('earth_night_4096.jpg');
    const surface = loadTexture('earth_bump_roughness_clouds_4096.jpg', false);
    const sun = new THREE.Vector3(-0.68, 0.49, 0.55).normalize();
    const earthVertex = /* glsl */ `
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
    `;
    const material = new THREE.ShaderMaterial({
        transparent: true,
        uniforms: {
            uDay: { value: day }, uNight: { value: night }, uSurface: { value: surface },
            uSun: { value: sun }, uOpacity: { value: 1 }, uFocus: { value: 0 },
            uCloudShadow: { value: 1 }, uGrid: { value: 0 },
            uTexel: { value: new THREE.Vector2(1 / 4096, 1 / 2048) },
        },
        vertexShader: earthVertex,
        fragmentShader: /* glsl */ `
            uniform sampler2D uDay;
            uniform sampler2D uNight;
            uniform sampler2D uSurface;
            uniform vec3 uSun;
            uniform vec2 uTexel;
            uniform float uOpacity;
            uniform float uFocus;
            uniform float uCloudShadow;
            uniform float uGrid;
            varying vec2 vUv;
            varying vec3 vNormalW;
            varying vec3 vPositionW;
            float surveyGrid(vec2 uv) {
                // True quarter-degree longitude/latitude lines; never synthetic market boundaries.
                vec2 degrees = vec2(uv.x * 360.0 - 180.0, uv.y * 180.0 - 90.0) / 0.25;
                vec2 cell = abs(fract(degrees - 0.5) - 0.5) / max(fwidth(degrees), vec2(1e-5));
                return 1.0 - min(min(cell.x, cell.y), 1.0);
            }
            void main() {
                vec3 baseNormal = normalize(vNormalW);
                vec3 view = normalize(cameraPosition - vPositionW);
                vec3 east = normalize(cross(vec3(0.0, 1.0, 0.0), baseNormal) + vec3(1e-5));
                vec3 north = cross(baseNormal, east);
                vec4 detail = texture2D(uSurface, vUv);
                float hx = texture2D(uSurface, vUv + vec2(uTexel.x, 0.0)).r - detail.r;
                float hy = texture2D(uSurface, vUv + vec2(0.0, uTexel.y)).r - detail.r;
                vec3 normal = normalize(baseNormal - (east * hx + north * hy) * 5.0);
                float light = dot(normal, uSun);
                float daylight = smoothstep(-0.18, 0.34, dot(baseNormal, uSun));
                float shadow = smoothstep(0.25, 1.0, texture2D(uSurface, vUv + vec2(0.0012, -0.0006)).b) * uCloudShadow;
                vec3 color = texture2D(uDay, vUv).rgb * (0.055 + 1.22 * max(light, 0.0)) * (1.0 - shadow * 0.36);
                vec3 halfway = normalize(uSun + view);
                float gloss = 1.0 - detail.g;
                float glint = pow(max(dot(baseNormal, halfway), 0.0), 420.0) * gloss * daylight;
                color += vec3(1.0, 0.84, 0.63) * glint * 0.34;
                color += texture2D(uNight, vUv).rgb * vec3(1.0, 0.76, 0.45) * pow(1.0 - daylight, 1.6) * 2.2;
                float limb = pow(1.0 - max(dot(baseNormal, view), 0.0), 2.6);
                color = mix(color, vec3(0.12, 0.35, 0.65) * (0.25 + daylight * 0.85), limb * 0.63);
                float luminance = dot(color, vec3(0.299, 0.587, 0.114));
                color = mix(color, vec3(luminance) * vec3(0.44, 0.64, 0.88), uFocus * 0.76);
                color *= 1.0 - uFocus * 0.24;
                color += vec3(0.30, 0.60, 0.86) * surveyGrid(vUv) * 0.17 * uGrid;
                gl_FragColor = vec4(color, uOpacity);
                #include <tonemapping_fragment>
                #include <colorspace_fragment>
            }
        `,
    });
    const sphere = new THREE.Mesh(new THREE.SphereGeometry(1, 144, 96), material);
    group.add(sphere);
    const clouds = new THREE.Mesh(new THREE.SphereGeometry(1.0022, 112, 72), new THREE.ShaderMaterial({
        transparent: true, depthWrite: false,
        uniforms: { uSurface: { value: surface }, uSun: { value: sun }, uOpacity: { value: 1 } },
        vertexShader: earthVertex,
        fragmentShader: /* glsl */ `
            uniform sampler2D uSurface;
            uniform vec3 uSun;
            uniform float uOpacity;
            varying vec2 vUv;
            varying vec3 vNormalW;
            void main() {
                float cover = smoothstep(0.28, 0.95, texture2D(uSurface, vUv).b);
                float light = max(dot(normalize(vNormalW), uSun), 0.0);
                vec3 color = vec3(0.91, 0.96, 1.0) * (0.13 + light * 1.05);
                gl_FragColor = vec4(color, cover * 0.76 * uOpacity);
                #include <tonemapping_fragment>
                #include <colorspace_fragment>
            }
        `,
    }));
    group.add(clouds);
    const halo = new THREE.Mesh(new THREE.SphereGeometry(1.024, 80, 48), new THREE.ShaderMaterial({
        transparent: true, depthWrite: false, side: THREE.BackSide,
        uniforms: { opacity: { value: 1 } },
        vertexShader: 'varying vec3 n; varying vec3 v; void main(){vec4 p=modelViewMatrix*vec4(position,1.);n=normalize(normalMatrix*normal);v=normalize(-p.xyz);gl_Position=projectionMatrix*p;}',
        fragmentShader: 'uniform float opacity;varying vec3 n;varying vec3 v;void main(){float rim=pow(1.-abs(dot(normalize(n),normalize(v))),2.5);gl_FragColor=vec4(.16,.45,.86,rim*.36*opacity);}',
    }));
    group.add(halo);
    const beacon = new THREE.Mesh(new THREE.SphereGeometry(0.006, 16, 12), new THREE.MeshBasicMaterial({ color: '#f2c990', transparent: true, depthTest: false }));
    group.add(beacon);
    const scopeOutline = new THREE.LineLoop(new THREE.BufferGeometry(), new THREE.LineBasicMaterial({ color: '#9bdcf1', transparent: true, opacity: 0, depthWrite: false }));
    group.add(scopeOutline);
    const original = (lat, lon) => {
        const phi = (lon + 180) * DEG, theta = (90 - lat) * DEG;
        return new THREE.Vector3(-Math.cos(phi) * Math.sin(theta), Math.cos(theta), Math.sin(phi) * Math.sin(theta));
    };
    let mapOpacity = 0;
    const arrivalPosition = new THREE.Vector3();
    return {
        get ready() { return ready || failed; },
        setFrame(frame) {
            // This radius matches the map's local longitude/latitude scale at Area 6.
            radius = frame.scale / DEG;
            const up = original(frame.lat, frame.lon);
            const east = original(frame.lat, frame.lon + 0.01).sub(up).normalize();
            const north = new THREE.Vector3().crossVectors(up, east).normalize();
            east.crossVectors(north, up).normalize();
            const basis = new THREE.Matrix4().makeBasis(east, up, north.clone().negate()).invert();
            sphere.quaternion.setFromRotationMatrix(basis);
            clouds.quaternion.copy(sphere.quaternion);
            group.scale.setScalar(radius);
            group.position.y = -radius;
            beacon.position.set(0, 1.008, 0);
            // Frame bounds come from the selected district geometries, matching the incoming map exactly.
            const bounds = frame.bounds;
            scopeOutline.visible = Boolean(bounds && [bounds.minLon, bounds.maxLon, bounds.minLat, bounds.maxLat].every(Number.isFinite));
            if (scopeOutline.visible) {
                const corners = [[bounds.minLon, bounds.minLat], [bounds.maxLon, bounds.minLat], [bounds.maxLon, bounds.maxLat], [bounds.minLon, bounds.maxLat]];
                const points = [];
                corners.forEach(([lon, lat], index) => {
                    const next = corners[(index + 1) % corners.length];
                    for (let step = 0; step < 16; step += 1) points.push(original(lerp(lat, next[1], step / 16), lerp(lon, next[0], step / 16)).applyMatrix4(basis).multiplyScalar(1.00012));
                });
                scopeOutline.geometry.dispose();
                scopeOutline.geometry = new THREE.BufferGeometry().setFromPoints(points);
            }
        },
        update(progress, still, camera, mapDistance, time = 0) {
            if (still || failed) {
                group.visible = false;
                mapOpacity = 1;
                return { opacity: 1, flight: false };
            }
            const zoom = smooth(0.07, 0.46, progress);
            const dissolve = smooth(0.245, 0.43, progress);
            mapOpacity = dissolve;
            group.visible = ready && dissolve < 0.999;
            material.uniforms.uOpacity.value = 1 - smooth(0.27, 0.405, progress);
            material.uniforms.uFocus.value = smooth(0.22, 0.36, progress);
            material.uniforms.uGrid.value = smooth(0.21, 0.29, progress) * (1 - smooth(0.35, 0.405, progress));
            material.uniforms.uCloudShadow.value = 1 - smooth(0.22, 0.34, progress);
            material.depthWrite = dissolve < 0.01;
            clouds.material.uniforms.uOpacity.value = (1 - smooth(0.21, 0.34, progress)) * material.uniforms.uOpacity.value;
            clouds.visible = clouds.material.uniforms.uOpacity.value > 0.001;
            clouds.quaternion.copy(sphere.quaternion);
            clouds.rotateY(time * 0.0006);
            halo.material.uniforms.opacity.value = 1 - smooth(0.19, 0.31, progress);
            beacon.material.opacity = (1 - smooth(0.20, 0.34, progress)) * 0.9;
            beacon.scale.setScalar(lerp(1, 0.04, smooth(0.08, 0.30, progress)));
            scopeOutline.material.opacity = smooth(0.10, 0.23, progress) * (1 - smooth(0.34, 0.43, progress)) * 0.72;
            const elevation = lerp(84, 89.8, smooth(0.08, 0.31, progress)) * DEG;
            const altitude = Math.exp(lerp(Math.log(radius * Math.max(3.3, 3.1 / camera.aspect)), Math.log(mapDistance), zoom));
            camera.position.set(0, -radius + Math.sin(elevation) * (radius + altitude), Math.cos(elevation) * (radius + altitude));
            // Converge to the exact local camera before handover, including narrow branch frames.
            const arrival = smooth(0.32, 0.49, progress);
            arrivalPosition.set(0, Math.sin(89.8 * DEG) * mapDistance, Math.cos(89.8 * DEG) * mapDistance);
            camera.position.lerp(arrivalPosition, arrival);
            camera.lookAt(0, lerp(-radius, 0, smooth(0.05, 0.30, progress)) + 0.03 * arrival, 0);
            camera.far = radius * 8;
            host.dataset.flight = progress < 0.31 ? 'earth' : progress < 0.49 ? 'arrival' : 'map';
            return { opacity: mapOpacity, flight: progress < 0.49 };
        },
        dispose() {
            disposed = true;
            group.removeFromParent();
            textures.forEach((texture) => texture.dispose());
            group.traverse((object) => { object.geometry?.dispose(); object.material?.dispose(); });
        },
    };
}
