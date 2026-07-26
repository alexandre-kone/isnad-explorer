import { Controller } from '@hotwired/stimulus';
import { Network } from 'vis-network';
import { DataSet } from 'vis-data/peer/esm/vis-data.js';

/*
 * Îlot Stimulus « network » : la scène de graphe de la vue Réseau.
 *
 * Portage du module du wireframe (page-2.html). Deux écarts assumés :
 *  - les données viennent de /api/isnad au lieu d'un window.HADITHS inline ;
 *  - l'état vit dans le contrôleur plutôt que dans des variables globales, et
 *    les contrôles sont câblés par data-action au lieu de onclick + window.*.
 */
export default class extends Controller {
    static targets = [
        'canvas', 'axis', 'genChips', 'colChips', 'tip', 'fiche',
        'hadithButtonAr', 'hadithButtonFr', 'hadithMenu',
        'capTitle', 'capIntro', 'physicsButton', 'modeToggle',
        'search', 'results',
    ];

    static values = { endpoint: String };

    async connect() {
        this.periods = {};
        this.hadiths = {};
        this.rawis = {};
        this.links = [];
        this.parents = {};
        this.children = {};
        this.physicsOn = true;
        this.mode = 'strata';
        this.activeGens = new Set();
        this.activeCol = null;
        this.focusSet = null;
        this.lastPointer = { x: 0, y: 0 };
        this.tipVisible = false;
        this.keyboardIndex = -1;

        this.onDocumentClick = (event) => {
            if (!event.target.closest('.searchwrap')) this.hideResults();
            if (!event.target.closest('.hsel')) this.hadithMenuTarget.classList.remove('show');
        };
        document.addEventListener('click', this.onDocumentClick);

        const response = await fetch(this.endpointValue, { headers: { Accept: 'application/json' } });
        if (!response.ok) {
            this.capIntroTarget.textContent = 'Le graphe n\'a pas pu être chargé.';
            return;
        }

        const payload = await response.json();
        this.periods = payload.periods;
        this.hadiths = payload.hadiths;

        this.renderAxis();
        this.renderHadithMenu();

        // Les libellés des nœuds sont mesurés par vis : attendre les polices
        // évite un premier rendu calé sur la police de repli.
        await (document.fonts ? document.fonts.ready : Promise.resolve());

        this.loadHadith(Object.keys(this.hadiths)[0]);
        this.element.dataset.hydrated = 'true';
    }

    disconnect() {
        this.cancelAutoFocus();
        document.removeEventListener('click', this.onDocumentClick);
        this.network?.destroy();
        this.network = null;
    }

    // ── définitions visuelles ────────────────────────────────────────────────

    nodeBase(id, r) {
        const p = this.periods[r.gen];
        const isCol = r.gen === 'collecteur';

        return {
            id,
            level: r.lvl,
            label: `${r.name.replace(' ﷺ', '')}\n${r.ar ?? ''}`,
            shape: isCol ? 'diamond' : 'dot',
            size: 0 === r.lvl ? 28 : r.pivot ? 27 : isCol ? 21 : 17,
            color: {
                background: isCol ? '#f4eedf' : p.color,
                border: isCol ? '#b08d3f' : (r.pivot ? '#b08d3f' : p.color),
                highlight: { background: isCol ? '#fbf6e6' : p.color, border: '#cba34a' },
                hover: { background: isCol ? '#fbf6e6' : p.color, border: '#cba34a' },
            },
            borderWidth: r.pivot ? 4 : (isCol ? 3 : 1.5),
            borderWidthSelected: r.pivot ? 5 : 4,
            font: {
                face: 'Amiri',
                color: isCol ? '#1f3a30' : '#2a2419',
                size: 0 === r.lvl || r.pivot ? 16 : 14,
                background: 'rgba(250,246,236,0.80)',
                strokeWidth: 0,
            },
            opacity: 1,
        };
    }

    edgeBase([from, to, gharib]) {
        return {
            id: `${from}>${to}`,
            from,
            to,
            arrows: { to: { enabled: true, scaleFactor: 0.5 } },
            gharib: !!gharib,
            color: gharib
                ? { color: '#b08d3f', highlight: '#cba34a', opacity: 0.95 }
                : { color: '#3a7a5f', highlight: '#14503b', opacity: 0.4 },
            width: gharib ? 4 : 1.7,
            smooth: { enabled: true, type: 'continuous', roundness: 0.45 },
        };
    }

    optionsFor(mode) {
        const common = {
            nodes: {
                shadow: { enabled: true, color: 'rgba(60,45,20,0.12)', size: 8, x: 0, y: 3 },
                shapeProperties: { interpolation: false },
            },
            edges: { selectionWidth: 1.4 },
            interaction: {
                hover: true, tooltipDelay: 100000, dragNodes: true,
                zoomView: true, dragView: true, hoverConnectedEdges: false,
            },
        };

        if ('strata' === mode) {
            return {
                ...common,
                layout: {
                    hierarchical: {
                        enabled: true, direction: 'UD', levelSeparation: 130, nodeSpacing: 116,
                        treeSpacing: 170, blockShifting: true, edgeMinimization: true,
                        parentCentralization: true, shakeTowards: 'roots',
                    },
                },
                physics: {
                    enabled: this.physicsOn, solver: 'hierarchicalRepulsion',
                    hierarchicalRepulsion: {
                        nodeDistance: 150, springLength: 120, springConstant: 0.012,
                        damping: 0.6, avoidOverlap: 1,
                    },
                    stabilization: { iterations: 280 },
                },
            };
        }

        return {
            ...common,
            layout: { hierarchical: { enabled: false }, improvedLayout: true },
            physics: {
                enabled: this.physicsOn, solver: 'forceAtlas2Based',
                forceAtlas2Based: {
                    gravitationalConstant: -58, centralGravity: 0.012, springLength: 150,
                    springConstant: 0.085, damping: 0.5, avoidOverlap: 0.75,
                },
                stabilization: { iterations: 220 }, maxVelocity: 30, minVelocity: 0.6,
            },
        };
    }

    // ── adjacence ────────────────────────────────────────────────────────────

    buildAdjacency() {
        this.parents = {};
        this.children = {};
        Object.keys(this.rawis).forEach((id) => {
            this.parents[id] = [];
            this.children[id] = [];
        });
        this.links.forEach(([f, t]) => {
            this.children[f]?.push(t);
            this.parents[t]?.push(f);
        });
    }

    walk(id, map, acc) {
        (map[id] || []).forEach((n) => {
            if (!acc.has(n)) {
                acc.add(n);
                this.walk(n, map, acc);
            }
        });

        return acc;
    }

    pathSet(id) {
        const s = new Set([id]);
        this.walk(id, this.parents, s);
        this.walk(id, this.children, s);

        return s;
    }

    ancestorSet(id) {
        const s = new Set([id]);
        this.walk(id, this.parents, s);

        return s;
    }

    // ── état de rendu (filtres + emphase) ────────────────────────────────────

    isStrong(id) {
        const r = this.rawis[id];
        if (!this.activeGens.has(r.gen)) return false;

        return !(this.focusSet && !this.focusSet.has(id));
    }

    applyState() {
        const nodeUpdates = Object.keys(this.rawis).map((id) => {
            const r = this.rawis[id];
            const on = this.isStrong(id);
            const isCol = 'collecteur' === r.gen;

            return {
                id,
                opacity: on ? 1 : 0.12,
                font: {
                    color: on ? (isCol ? '#1f3a30' : '#2a2419') : 'rgba(42,36,25,0.16)',
                    background: on ? 'rgba(250,246,236,0.80)' : 'rgba(250,246,236,0.0)',
                    face: 'Amiri',
                    size: 0 === r.lvl || r.pivot ? 16 : 14,
                    strokeWidth: 0,
                },
            };
        });

        const edgeUpdates = this.edges.map((e) => {
            const on = this.isStrong(e.from) && this.isStrong(e.to);

            return {
                id: e.id,
                color: {
                    color: e.gharib ? '#b08d3f' : '#3a7a5f',
                    highlight: e.gharib ? '#cba34a' : '#14503b',
                    opacity: on ? (e.gharib ? 0.95 : 0.4) : 0.06,
                },
            };
        });

        this.nodes.update(nodeUpdates);
        this.edges.update(edgeUpdates);
    }

    // ── construction du graphe ───────────────────────────────────────────────

    buildGraph() {
        this.nodes = new DataSet(Object.entries(this.rawis).map(([id, r]) => this.nodeBase(id, r)));
        this.edges = new DataSet(this.links.map((l) => this.edgeBase(l)));
        this.network = new Network(this.canvasTarget, { nodes: this.nodes, edges: this.edges }, this.optionsFor(this.mode));

        this.network.on('click', (p) => {
            if (p.nodes.length) this.selectNode(p.nodes[0]);
            else this.clearFocus();
        });
        this.network.on('stabilizationIterationsDone', () => this.network.fit({ animation: { duration: 600 } }));
        this.network.on('hoverNode', (p) => {
            document.body.style.cursor = 'pointer';
            this.showTip(p.node);
        });
        this.network.on('blurNode', () => {
            document.body.style.cursor = 'default';
            this.hideTip();
        });
        this.network.on('dragStart', () => this.hideTip());
        this.network.on('zoom', () => this.hideTip());

        this.canvasTarget.addEventListener('mousemove', (e) => {
            const r = this.canvasTarget.getBoundingClientRect();
            this.lastPointer = { x: e.clientX - r.left, y: e.clientY - r.top };
            if (this.tipVisible) this.positionTip();
        });
    }

    // ── fiche du maillon ─────────────────────────────────────────────────────

    /**
     * Le cadrage initial sur le pivot est différé, le temps que la simulation
     * place les nœuds. Toute action de l'utilisateur avant l'échéance l'annule :
     * sinon la sélection différée écraserait son choix.
     */
    cancelAutoFocus() {
        clearTimeout(this.pivotTimer);
        this.pivotTimer = null;
    }

    selectNode(id) {
        const r = this.rawis[id];
        if (!r) return;

        this.cancelAutoFocus();
        this.focusSet = this.pathSet(id);
        this.activeCol = 'collecteur' === r.gen ? id : null;
        this.syncColChips();
        this.applyState();
        this.showFiche(id);
    }

    showFiche(id) {
        const r = this.rawis[id];
        const p = this.periods[r.gen];
        const isCol = 'collecteur' === r.gen;

        const rels = [];
        if (r.up) rels.push(`<div class="rel"><div class="rl">↑ Reçoit de</div><div class="rv">${esc(r.up)}</div></div>`);
        if (r.down) rels.push(`<div class="rel"><div class="rl">↓ Transmet à</div><div class="rv">${esc(r.down)}</div></div>`);

        const stat = r.pivot
            ? `<div class="stat"><b>${esc(r.chains ?? '')}</b><span>voies (turuq) connues<br>convergent par ce maillon</span></div>`
            : r.work
                ? `<div class="field"><div class="fl">Recueil</div><div class="fv">${esc(r.work)}</div></div>`
                : '';

        this.ficheTarget.innerHTML = `
            <div class="band" style="background:${isCol ? '#0f3f2e' : p.color}">
                ${r.pivot ? '<span class="pivotflag">Pivot · مدار</span>' : ''}
                <div class="ar">${esc(r.ar ?? '')}</div>
                <b>${esc(r.name.replace(' ﷺ', ''))}</b>
                <span class="pb">${esc(p.fr)}</span>
            </div>
            <div class="cbody">
                ${r.meta ? `<div class="field"><div class="fl">Période &amp; lieu</div><div class="fv">${esc(r.meta)}</div></div>` : ''}
                ${r.region ? `<div class="field"><div class="fl">Région</div><div class="fv">${esc(r.region)}</div></div>` : ''}
                ${r.role ? `<div class="field"><div class="fl">Rôle</div><div class="fv">${esc(r.role)}</div></div>` : ''}
                ${stat}
                ${r.bio ? `<div class="bio">${esc(r.bio)}</div>` : ''}
                <div class="relgrid">${rels.join('')}</div>
                <button class="pathbtn" data-action="network#clearFocus">↺ Afficher tout le réseau</button>
            </div>`;
    }

    clearFocus() {
        this.focusSet = null;
        this.activeCol = null;
        this.syncColChips();
        this.applyState();
    }

    // ── infobulle ────────────────────────────────────────────────────────────

    showTip(id) {
        const r = this.rawis[id];
        if (!r) return;

        const p = this.periods[r.gen];
        const isCol = 'collecteur' === r.gen;

        this.tipTarget.innerHTML = `
            <div class="tb" style="background:${isCol ? '#0f3f2e' : p.color}">
                <div class="tar">${esc(r.ar ?? '')}</div>
                <b>${esc(r.name.replace(' ﷺ', ''))}</b>
                <div class="tg">${esc(p.fr)}</div>
                ${r.pivot ? '<span class="tpivot">Pivot · مدار</span>' : ''}
            </div>
            <div class="tbody">
                ${r.meta ? `<div class="trow"><span class="k">Période</span><span>${esc(r.meta)}</span></div>` : ''}
                ${r.region ? `<div class="trow"><span class="k">Région</span><span>${esc(r.region)}</span></div>` : ''}
                ${r.role ? `<div class="trow"><span class="k">Rôle</span><span>${esc(r.role)}</span></div>` : ''}
                ${r.work ? `<div class="trow"><span class="k">Recueil</span><span>${esc(r.work)}</span></div>` : ''}
                ${r.pivot ? `<div class="trow"><span class="k">Voies</span><span>${esc(r.chains ?? '')} turuq convergent ici</span></div>` : ''}
                ${r.bio ? `<div class="tnote">${esc(r.bio)}</div>` : ''}
            </div>`;

        this.tipVisible = true;
        this.positionTip();
        this.tipTarget.classList.add('show');
    }

    positionTip() {
        const tip = this.tipTarget;
        const stage = this.canvasTarget.getBoundingClientRect();
        const w = tip.offsetWidth || 230;
        const h = tip.offsetHeight || 150;

        let x = this.lastPointer.x + 18;
        let y = this.lastPointer.y + 18;
        if (x + w > stage.width - 8) x = this.lastPointer.x - w - 14;
        if (y + h > stage.height - 8) y = Math.max(8, this.lastPointer.y - h - 14);

        tip.style.left = `${x}px`;
        tip.style.top = `${y}px`;
    }

    hideTip() {
        this.tipVisible = false;
        this.tipTarget.classList.remove('show');
    }

    // ── filtres ──────────────────────────────────────────────────────────────

    orderedPeriods() {
        return Object.entries(this.periods).sort((a, b) => a[1].order - b[1].order);
    }

    renderChips() {
        this.genChipsTarget.innerHTML = this.orderedPeriods().map(([k, p]) =>
            `<span class="chip gen" data-gen="${esc(k)}"><i class="${'collecteur' === k ? 'hex' : ''}" style="background:${p.color}"></i>${esc(p.fr.split(' · ')[0])}</span>`).join('');

        this.genChipsTarget.querySelectorAll('.chip').forEach((c) => {
            c.onclick = () => {
                const g = c.dataset.gen;
                if (this.activeGens.has(g)) this.activeGens.delete(g);
                else this.activeGens.add(g);
                c.classList.toggle('off', !this.activeGens.has(g));
                this.applyState();
            };
        });

        const cols = Object.entries(this.rawis).filter(([, r]) => 'collecteur' === r.gen);
        this.colChipsTarget.innerHTML = cols.map(([id, r]) =>
            `<span class="chip col" data-col="${esc(id)}"><i class="hex" style="background:#b08d3f"></i>${esc(r.name)}</span>`).join('');

        this.colChipsTarget.querySelectorAll('.chip').forEach((c) => {
            c.onclick = () => {
                const id = c.dataset.col;
                this.cancelAutoFocus();
                if (this.activeCol === id) {
                    this.clearFocus();

                    return;
                }
                this.activeCol = id;
                this.focusSet = this.ancestorSet(id);
                this.syncColChips();
                this.applyState();
                this.showFiche(id);
                this.network.focus(id, { scale: 0.9, animation: { duration: 600 } });
            };
        });
    }

    syncColChips() {
        this.colChipsTarget.querySelectorAll('.chip')
            .forEach((c) => c.classList.toggle('act', c.dataset.col === this.activeCol));
    }

    renderAxis() {
        this.axisTarget.innerHTML = '<span class="arr">▲</span>'
            + this.orderedPeriods().map(([, p]) =>
                `<div class="seg"><i style="background:${p.color}"></i><b>${esc(p.fr.split(' · ')[0])}</b></div>`).join('')
            + '<span class="lbl">temps →</span>';
    }

    // ── recherche live ───────────────────────────────────────────────────────

    search() {
        const q = normalise(this.searchTarget.value.trim());
        if (!q) {
            this.hideResults();

            return;
        }

        const raw = this.searchTarget.value.trim();
        const matches = Object.entries(this.rawis)
            .filter(([, r]) => normalise(r.name).includes(q) || (r.ar ?? '').includes(raw))
            .slice(0, 8);

        this.renderResults(matches);
    }

    renderResults(list) {
        this.matches = list;
        this.keyboardIndex = -1;

        if (!list.length) {
            this.resultsTarget.innerHTML = '<div class="res empty">Aucun narrateur trouvé</div>';
            this.resultsTarget.classList.add('show');

            return;
        }

        this.resultsTarget.innerHTML = list.map(([id, r]) => {
            const p = this.periods[r.gen];

            return `<div class="res" data-id="${esc(id)}"><div style="display:flex;align-items:center;gap:9px;">
                <span class="gdot" style="background:${p.color}"></span>
                <span class="rn">${esc(r.name.replace(' ﷺ', ''))}<small>${esc(p.fr.split(' · ')[0])}${r.meta ? ` · ${esc(r.meta)}` : ''}</small></span></div>
                <span class="rar">${esc(r.ar ?? '')}</span></div>`;
        }).join('');

        this.resultsTarget.classList.add('show');
        this.resultsTarget.querySelectorAll('.res[data-id]')
            .forEach((el) => el.onclick = () => this.pick(el.dataset.id));
    }

    navigate(event) {
        const rows = this.resultsTarget.querySelectorAll('.res[data-id]');

        if ('ArrowDown' === event.key) {
            this.keyboardIndex = Math.min(this.keyboardIndex + 1, rows.length - 1);
        } else if ('ArrowUp' === event.key) {
            this.keyboardIndex = Math.max(this.keyboardIndex - 1, 0);
        } else if ('Enter' === event.key) {
            if (rows.length) {
                event.preventDefault();
                this.pick((rows[Math.max(this.keyboardIndex, 0)] || rows[0]).dataset.id);
            }

            return;
        } else if ('Escape' === event.key) {
            this.hideResults();

            return;
        } else {
            return;
        }

        rows.forEach((r, i) => r.classList.toggle('kbd', i === this.keyboardIndex));
        event.preventDefault();
    }

    pick(id) {
        this.hideResults();
        this.searchTarget.value = this.rawis[id].name.replace(' ﷺ', '');
        this.network.focus(id, { scale: 1.2, animation: { duration: 700 } });
        this.selectNode(id);
    }

    hideResults() {
        this.resultsTarget?.classList.remove('show');
    }

    // ── sélecteur de hadith ──────────────────────────────────────────────────

    loadHadith(key) {
        const h = this.hadiths[key];
        if (!h) return;

        this.current = h;
        this.rawis = h.rawis;
        this.links = h.links;
        this.buildAdjacency();

        this.activeGens = new Set(Object.keys(this.periods));
        this.activeCol = null;
        this.focusSet = null;

        this.hadithButtonArTarget.textContent = h.ar ?? '';
        this.hadithButtonFrTarget.textContent = h.label;
        this.capTitleTarget.textContent = `Réseau de transmission — ${h.label}`;
        this.capIntroTarget.textContent = h.intro ?? '';

        this.renderChips();
        this.syncColChips();

        this.network?.destroy();
        this.buildGraph();

        if (h.pivot) {
            // Laisse la stabilisation placer les nœuds avant de cadrer le pivot.
            this.cancelAutoFocus();
            this.pivotTimer = setTimeout(() => this.selectNode(h.pivot), 350);
        }
    }

    renderHadithMenu() {
        this.hadithMenuTarget.innerHTML = Object.values(this.hadiths).map((h) =>
            `<div class="hopt ${h.ready ? '' : 'soon'}" data-key="${esc(h.key)}"><b>${esc(h.label)}</b><span>${esc(h.ref)}</span></div>`).join('');

        this.hadithMenuTarget.querySelectorAll('.hopt').forEach((o) => {
            o.onclick = () => {
                const h = this.hadiths[o.dataset.key];
                this.hadithMenuTarget.classList.remove('show');
                if (!h.ready) {
                    this.toast(`Données à intégrer — ${h.label}`);

                    return;
                }
                this.loadHadith(h.key);
            };
        });
    }

    toggleHadithMenu() {
        this.hadithMenuTarget.classList.toggle('show');
    }

    // ── contrôles ────────────────────────────────────────────────────────────

    fitView() {
        this.network?.fit({ animation: { duration: 700 } });
    }

    focusPivot() {
        if (!this.network || !this.current?.pivot) return;
        this.network.focus(this.current.pivot, { scale: 1.1, animation: { duration: 700 } });
        this.selectNode(this.current.pivot);
    }

    togglePhysics() {
        this.physicsOn = !this.physicsOn;
        this.network.setOptions({ physics: { enabled: this.physicsOn } });
        this.physicsButtonTarget.innerHTML = this.physicsOn ? '<b>❄</b> Figer' : '<b>✶</b> Réactiver';
    }

    setMode(event) {
        const chosen = event.currentTarget;
        if (chosen.classList.contains('on')) return;

        this.modeToggleTarget.querySelectorAll('div').forEach((x) => x.classList.remove('on'));
        chosen.classList.add('on');
        this.mode = chosen.dataset.mode;

        this.network.setOptions(this.optionsFor(this.mode));
        setTimeout(() => this.network.fit({ animation: { duration: 600 } }), 60);
    }

    toast(message) {
        let el = document.getElementById('toast');
        if (!el) {
            el = document.createElement('div');
            el.id = 'toast';
            document.body.appendChild(el);
        }
        el.textContent = message;
        el.style.opacity = '1';
        clearTimeout(this.toastTimer);
        this.toastTimer = setTimeout(() => el.style.opacity = '0', 1900);
    }
}

/* Les notices proviennent de la base : on les insère en HTML, donc on échappe. */
function esc(value) {
    return String(value).replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

function normalise(value) {
    return value.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
}
