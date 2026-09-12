@extends('layouts.app')
@section('title', 'Rack Monitoring')

@section('content')
    <div class="search-row">
        <input type="text" id="searchInput" class="search-input mono" placeholder="search box code or item name/part code...">
        <button class="search-btn" id="searchBtn">Search</button>
    </div>
    <div id="searchResult"></div>

    <div class="rack-tabs">
        @foreach ($racks as $r)
            <a href="{{ route('rack.index', ['rack' => $r]) }}" class="rack-tab mono {{ $r === $activeRack ? 'active' : '' }}">RACK {{ substr($r, 1) }}</a>
        @endforeach
    </div>

    <div class="rackmon-layout">
        {{-- ===== Tampak depan rak terpilih (3D) ===== --}}
        <div class="rack-front-panel">
            <div class="rack-front-title mono">RACK {{ substr($activeRack, 1) }} &middot; {{ $columns }} COLUMNS &times; {{ $rows }} LAYERS</div>
            <div id="rackFront3d" class="rack-front-3d"></div>
            <div class="rack-front-legend">
                <span><span class="legend-dot" style="background:linear-gradient(158deg,#818CF8,#4338CA)"></span>Filled (click a box for details)</span>
                <span><span class="legend-dot" style="background:#E8EEF5; border:1px solid #B7C2CC"></span>Empty</span>
                <span class="rack-front-hint">Drag to rotate &middot; scroll to zoom</span>
            </div>
        </div>

        {{-- ===== Right column: mini 3D isometric view + slot detail ===== --}}
        <div class="rackmon-side">
            <div class="rack3d-mini-wrap">
                <div id="rack3d" class="rack3d-mini"></div>
                <div class="rack3d-hint">Drag / scroll</div>
            </div>
            <div class="detail-panel">
                <div class="panel-title">Slot Detail</div>
                <div id="slotDetail">
                    <div class="detail-row"><span class="detail-key">Slot</span><span class="detail-val">-</span></div>
                    <div class="detail-row"><span class="detail-key">Status</span><span class="detail-val">Click a box</span></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>

<script>
    // =====================================================================
    // Scene 3D BESAR: rak terpilih, tampak depan, box (keranjang) bisa diklik.
    // =====================================================================
    (function initRackFront3D() {
        const COLUMNS = {{ $columns }};
        const ROWS = {{ $rows }};
        const RACK_SLOTS = @json($slotsData); // [{col,layer,status,storage_bin,code}]
        const container = document.getElementById('rackFront3d');
        if (!container || typeof THREE === 'undefined') return;

        const scene = new THREE.Scene();
        scene.background = new THREE.Color(0xE8EEF5);

        const W = container.clientWidth, H = container.clientHeight;
        const camera = new THREE.PerspectiveCamera(40, W / H, 0.1, 1000);

        const renderer = new THREE.WebGLRenderer({ antialias: true });
        renderer.setSize(W, H);
        renderer.setPixelRatio(window.devicePixelRatio || 1);
        container.appendChild(renderer.domElement);

        scene.add(new THREE.AmbientLight(0xffffff, 0.85));
        const dl = new THREE.DirectionalLight(0xffffff, 0.5);
        dl.position.set(6, 14, 16);
        scene.add(dl);

        const crateColor = 0x4F46E5, hitColor = 0xF59E0B;

        // Frame rak, ditengah di x=0, dasar y=0.
        const halfW = COLUMNS / 2 + 0.4;
        const halfD = 0.7;
        const topY = ROWS + 0.9;
        const postGeo = new THREE.BoxGeometry(0.17, topY, 0.17);
        const postMat = new THREE.MeshStandardMaterial({ color: 0x312E81 });
        [-1, 1].forEach(sx => [-1, 1].forEach(sz => {
            const p = new THREE.Mesh(postGeo, postMat);
            p.position.set(sx * halfW, topY / 2, sz * halfD);
            scene.add(p);
        }));
        const beamGeo = new THREE.BoxGeometry(COLUMNS + 1.0, 0.1, halfD * 2 + 0.16);
        const beamMat = new THREE.MeshStandardMaterial({ color: 0xC2410C });
        for (let l = 1; l <= ROWS; l++) {
            const b = new THREE.Mesh(beamGeo, beamMat);
            b.position.set(0, l - 0.42, 0);
            scene.add(b);
        }

        // Label kode (mis. R1C15) — dibuat sebagai canvas texture, ditempel di
        // sisi depan + kedua sisi samping keranjang.
        function makeLabelTexture(code) {
            const c = document.createElement('canvas');
            c.width = 320; c.height = 128;
            const ctx = c.getContext('2d');
            ctx.fillStyle = 'rgba(244,248,252,0.95)';
            ctx.fillRect(8, 26, 304, 76);
            ctx.strokeStyle = 'rgba(67,56,202,0.9)';
            ctx.lineWidth = 4;
            ctx.strokeRect(8, 26, 304, 76);
            ctx.fillStyle = '#312E81';
            ctx.font = 'bold 46px "JetBrains Mono", monospace';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(code, 160, 66);
            const t = new THREE.CanvasTexture(c);
            t.anisotropy = 4;
            return t;
        }
        const labelGeo = new THREE.PlaneGeometry(0.74, 0.30);
        function addCrateLabels(mesh, code) {
            const mat = new THREE.MeshBasicMaterial({ map: makeLabelTexture(code), transparent: true });
            const faces = [
                { pos: [0, 0, 0.481], rotY: 0 },             // depan (+z)
                { pos: [0, 0, -0.481], rotY: Math.PI },       // belakang (-z)
                { pos: [0.442, 0, 0], rotY: Math.PI / 2 },    // samping kanan (+x)
                { pos: [-0.442, 0, 0], rotY: -Math.PI / 2 },  // samping kiri (-x)
            ];
            faces.forEach(f => {
                const p = new THREE.Mesh(labelGeo, mat);
                p.position.set(...f.pos);
                p.rotation.y = f.rotY;
                mesh.add(p);
            });
        }

        // Keranjang: hanya slot FULL.
        const crateGeo = new THREE.BoxGeometry(0.88, 0.8, 0.95);
        const crateMeshes = [];
        RACK_SLOTS.forEach(s => {
            if (s.status !== 'FULL') return;
            const mat = new THREE.MeshStandardMaterial({ color: crateColor });
            const mesh = new THREE.Mesh(crateGeo, mat);
            // Kolom 1 di kanan (x negatif), kolom 9 di kiri (x positif).
            const x = ((COLUMNS + 1) / 2 - s.col) * 1.0;
            mesh.position.set(x, s.layer * 1.0, 0);
            mesh.userData = { storage_bin: s.storage_bin, code: s.code };
            addCrateLabels(mesh, s.code);
            scene.add(mesh);
            crateMeshes.push(mesh);
        });

        // Kamera: tampak depan, sedikit menyudut & agak dari atas.
        let rotY = 0.22, rotX = -0.14, dist = COLUMNS * 1.5;
        function applyCam() {
            const cx = dist * Math.sin(rotY) * Math.cos(rotX);
            const cz = dist * Math.cos(rotY) * Math.cos(rotX);
            const cy = ROWS / 2 + dist * Math.sin(-rotX);
            camera.position.set(cx, cy, cz);
            camera.lookAt(0, ROWS / 2, 0);
        }

        // Raycasting untuk klik box.
        const raycaster = new THREE.Raycaster();
        const pointer = new THREE.Vector2();
        let selected = null;
        function selectMesh(m) {
            if (selected) selected.material.emissive && selected.material.emissive.setHex(0x000000);
            selected = m;
            if (m) m.material.emissive.setHex(0x2a2a2a);
        }

        let isDragging = false, moved = false, lastX = 0, lastY = 0;
        renderer.domElement.addEventListener('pointerdown', e => { isDragging = true; moved = false; lastX = e.clientX; lastY = e.clientY; });
        window.addEventListener('pointerup', e => {
            if (isDragging && !moved) {
                const rect = renderer.domElement.getBoundingClientRect();
                pointer.x = ((e.clientX - rect.left) / rect.width) * 2 - 1;
                pointer.y = -((e.clientY - rect.top) / rect.height) * 2 + 1;
                raycaster.setFromCamera(pointer, camera);
                const hits = raycaster.intersectObjects(crateMeshes);
                if (hits.length) {
                    selectMesh(hits[0].object);
                    window.showSlotDetailByBin(hits[0].object.userData.storage_bin);
                }
            }
            isDragging = false;
        });
        window.addEventListener('pointermove', e => {
            if (!isDragging) return;
            if (Math.abs(e.clientX - lastX) + Math.abs(e.clientY - lastY) > 3) moved = true;
            rotY += (e.clientX - lastX) * 0.006;
            rotX = Math.max(-0.5, Math.min(0.35, rotX + (e.clientY - lastY) * 0.005));
            lastX = e.clientX; lastY = e.clientY;
        });
        renderer.domElement.addEventListener('wheel', e => {
            e.preventDefault();
            dist = Math.max(COLUMNS * 0.9, Math.min(COLUMNS * 2.4, dist + e.deltaY * 0.02));
        }, { passive: false });

        function animate() {
            requestAnimationFrame(animate);
            applyCam();
            renderer.render(scene, camera);
        }
        applyCam();
        animate();

        window.addEventListener('resize', () => {
            const w = container.clientWidth, h = container.clientHeight;
            camera.aspect = w / h; camera.updateProjectionMatrix();
            renderer.setSize(w, h);
        });

        // Dipakai pencarian & auto-highlight: sorot box dengan kode tertentu.
        window.rackFrontFocus = function (code) {
            crateMeshes.forEach(m => m.material.color.setHex(crateColor));
            const m = crateMeshes.find(x => x.userData.code === code);
            if (m) {
                m.material.color.setHex(hitColor);
                selectMesh(m);
                window.showSlotDetailByBin(m.userData.storage_bin);
            }
        };
        window.rackFrontClearHighlight = function () {
            crateMeshes.forEach(m => m.material.color.setHex(crateColor));
        };
    })();
</script>

<script>
    // =====================================================================
    // Iso 3D KECIL: semua rack. Rack selain aktif di-abu-abukan kalau user
    // sudah memilih lewat tab (RACK_EXPLICIT). Baru masuk menu -> full warna.
    // =====================================================================
    (function initRack3D() {
        const COLUMNS = {{ $columns }};
        const ROWS = {{ $rows }};
        const ACTIVE_RACK = @json($activeRack);
        const RACK_EXPLICIT = @json($rackExplicit);
        const RACK_ORDER_3D = ['R1', 'R5', 'R2', 'R6', 'R3', 'R7', 'R4', 'R8'];
        const container = document.getElementById('rack3d');
        if (!container || typeof THREE === 'undefined') return;

        const scene = new THREE.Scene();
        scene.background = new THREE.Color(0xF8FAFC);

        const width = container.clientWidth, height = container.clientHeight;
        const camera = new THREE.PerspectiveCamera(45, width / height, 0.1, 1000);

        const renderer = new THREE.WebGLRenderer({ antialias: true });
        renderer.setSize(width, height);
        renderer.setPixelRatio(window.devicePixelRatio || 1);
        container.appendChild(renderer.domElement);

        scene.add(new THREE.AmbientLight(0xffffff, 0.8));
        const dirLight = new THREE.DirectionalLight(0xffffff, 0.55);
        dirLight.position.set(10, 20, 15);
        scene.add(dirLight);

        const frameColor = 0x312E81, beamColor = 0xC2410C, crateColor = 0x4F46E5, grayColor = 0xB2BAC3;
        const crateGeo = new THREE.BoxGeometry(0.82, 0.72, 0.82);
        const rackGroup = new THREE.Group();
        scene.add(rackGroup);

        const pairGapX = COLUMNS + 1.6, depthGapZ = 5.5;
        const meshesByCode = {};
        const rackObjects = {};

        function buildRackFrame(originX, originZ, mats) {
            const halfW = COLUMNS / 2 + 0.25, halfD = 0.65, topY = ROWS * 1.0 + 0.6;
            const postGeo = new THREE.BoxGeometry(0.14, topY, 0.14);
            const postMat = new THREE.MeshStandardMaterial({ color: frameColor });
            mats.push(postMat);
            [-1, 1].forEach(sx => [-1, 1].forEach(sz => {
                const post = new THREE.Mesh(postGeo, postMat);
                post.position.set(originX + sx * halfW, topY / 2, originZ + sz * halfD);
                rackGroup.add(post);
            }));
            const beamGeo = new THREE.BoxGeometry(COLUMNS + 0.5, 0.07, halfD * 2 + 0.1);
            const beamMat = new THREE.MeshStandardMaterial({ color: beamColor });
            mats.push(beamMat);
            for (let l = 1; l <= ROWS; l++) {
                const beam = new THREE.Mesh(beamGeo, beamMat);
                beam.position.set(originX, l * 1.0 - 0.4, originZ);
                rackGroup.add(beam);
            }
        }

        RACK_ORDER_3D.forEach((rackName, idx) => {
            const pairIdx = Math.floor(idx / 2), sideIdx = idx % 2;
            const originX = sideIdx === 0 ? pairGapX / 2 : -pairGapX / 2;
            const originZ = (1.5 - pairIdx) * depthGapZ;

            const label = makeTextSprite(rackName);
            label.position.set(originX, ROWS + 2.1, originZ);
            label.scale.set(2.6, 1.3, 1);
            rackGroup.add(label);

            const mats = [];
            buildRackFrame(originX, originZ, mats);
            for (let c = 1; c <= COLUMNS; c++) {
                for (let l = 1; l <= ROWS; l++) {
                    const mat = new THREE.MeshStandardMaterial({ color: crateColor });
                    mats.push(mat);
                    const mesh = new THREE.Mesh(crateGeo, mat);
                    mesh.position.set(originX + ((COLUMNS + 1) / 2 - c) * 1.0, l * 1.0, originZ);
                    mesh.visible = false;
                    rackGroup.add(mesh);
                    meshesByCode[`${rackName}C${c}${l}`] = mesh;
                }
            }
            rackObjects[rackName] = { mats, orig: mats.map(m => m.color.getHex()) };
        });

        function setRackGrayed(rackName, gray) {
            const o = rackObjects[rackName];
            if (!o) return;
            o.mats.forEach((m, i) => m.color.setHex(gray ? grayColor : o.orig[i]));
        }
        if (RACK_EXPLICIT) {
            RACK_ORDER_3D.forEach(r => { if (r !== ACTIVE_RACK) setRackGrayed(r, true); });
        }

        function makeTextSprite(text) {
            const canvas = document.createElement('canvas');
            canvas.width = 128; canvas.height = 64;
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#312E81';
            ctx.font = 'bold 34px Inter, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(text, 64, 42);
            const sprite = new THREE.Sprite(new THREE.SpriteMaterial({ map: new THREE.CanvasTexture(canvas), transparent: true }));
            sprite.scale.set(4, 2, 1);
            return sprite;
        }

        fetch('{{ route('rack.all-slots') }}').then(r => r.json()).then(data => {
            Object.keys(data).forEach(rackName => {
                data[rackName].forEach(slot => {
                    const mesh = meshesByCode[slot.code];
                    if (mesh && slot.status === 'FULL') mesh.visible = true;
                });
            });
        }).catch(() => {});

        let autoRotate = true, isDragging = false, lastX = 0, lastY = 0;
        let rotY = 0.1, rotX = -0.26;
        function applyCamera() {
            const radius = 26;
            camera.position.x = radius * Math.sin(rotY) * Math.cos(rotX);
            camera.position.z = radius * Math.cos(rotY) * Math.cos(rotX);
            camera.position.y = 14 + radius * Math.sin(rotX) * -1;
            camera.lookAt(0, 4, 0);
        }
        container.addEventListener('pointerdown', e => { isDragging = true; autoRotate = false; lastX = e.clientX; lastY = e.clientY; });
        window.addEventListener('pointerup', () => { isDragging = false; });
        window.addEventListener('pointermove', e => {
            if (!isDragging) return;
            rotY += (e.clientX - lastX) * 0.005;
            rotX = Math.max(-0.6, Math.min(0.6, rotX + (e.clientY - lastY) * 0.005));
            lastX = e.clientX; lastY = e.clientY;
        });
        container.addEventListener('wheel', e => {
            e.preventDefault();
            camera.fov = Math.max(20, Math.min(70, camera.fov + e.deltaY * 0.02));
            camera.updateProjectionMatrix();
        }, { passive: false });
        function animate() {
            requestAnimationFrame(animate);
            if (autoRotate) rotY += 0.0025;
            applyCamera();
            renderer.render(scene, camera);
        }
        applyCamera();
        animate();
        window.addEventListener('resize', () => {
            const w = container.clientWidth, h = container.clientHeight;
            camera.aspect = w / h; camera.updateProjectionMatrix();
            renderer.setSize(w, h);
        });
    })();
</script>

<script>
    function renderDetail(d) {
        const box = document.getElementById('slotDetail');
        if (d.status === 'FULL' && d.qr_id) {
            const items = (d.items || []).map(it => `
                <div class="item-row">
                    <div class="item-name">${it.component_name}<span class="item-sku">${it.component}</span></div>
                    <div class="item-qty">${it.qty} ${it.unit}</div>
                </div>`).join('') || '<div class="item-row"><div class="item-name">No item data recorded</div></div>';
            box.innerHTML = `
                <div class="detail-row"><span class="detail-key">Slot</span><span class="detail-val">${d.code}</span></div>
                <div class="detail-row"><span class="detail-key">Status</span><span class="detail-val">Filled</span></div>
                <div class="detail-row"><span class="detail-key">Box code</span><span class="detail-val">${d.qr_id}</span></div>
                <div class="detail-row"><span class="detail-key">Last updated</span><span class="detail-val">${d.upd_date ?? '-'}</span></div>
                <div class="detail-subtitle">BOX CONTENTS &middot; ${(d.items || []).length} ITEM TYPES</div>
                <div class="item-list">${items}</div>`;
        } else {
            box.innerHTML = `
                <div class="detail-row"><span class="detail-key">Slot</span><span class="detail-val">${d.code}</span></div>
                <div class="detail-row"><span class="detail-key">Status</span><span class="detail-val">${d.status || 'Empty'}</span></div>`;
        }
    }

    window.showSlotDetailByBin = async function (storageBin) {
        const res = await fetch(`{{ url('rack/slot') }}/${storageBin}`);
        if (res.ok) renderDetail(await res.json());
    };

    async function doSearch() {
        const q = document.getElementById('searchInput').value.trim();
        const resultEl = document.getElementById('searchResult');
        if (window.rackFrontClearHighlight) window.rackFrontClearHighlight();
        if (!q) { resultEl.innerHTML = ''; return; }
        const res = await fetch(`{{ route('rack.search') }}?q=${encodeURIComponent(q)}`);
        const data = await res.json();
        if (!data.length) {
            resultEl.innerHTML = '<div class="note">Not found.</div>';
            return;
        }
        const chips = data.map(d => {
            if (d.rack === '{{ $activeRack }}') {
                if (window.rackFrontFocus) window.rackFrontFocus(d.code);
                return `<span class="search-chip local mono">${d.code} <span class="chip-sep">&middot;</span> ${d.qr_id}</span>`;
            }
            return `<a href="{{ url('rack') }}?rack=${d.rack}&highlight=${d.code}" class="search-chip remote mono">${d.code} <span class="chip-sep">&middot;</span> ${d.qr_id} <span class="chip-jump">Rack ${d.rack.replace('R','')} &rarr;</span></a>`;
        }).join('');
        resultEl.innerHTML = `<div class="search-result-wrap"><span class="search-result-label">Found</span>${chips}</div>`;
    }
    document.getElementById('searchBtn').addEventListener('click', doSearch);
    document.getElementById('searchInput').addEventListener('keydown', e => { if (e.key === 'Enter') doSearch(); });

    @if ($highlightCode)
        window.addEventListener('load', () => {
            if (window.rackFrontFocus) window.rackFrontFocus('{{ $highlightCode }}');
        });
    @endif
</script>
@endpush
