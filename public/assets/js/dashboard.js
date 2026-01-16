/**
 * Dashboard JavaScript
 * Handles evidence listing, filtering, grouped views, and multiple view modes
 */

(function () {
    // State
    const state = {
        token: localStorage.getItem('authToken'),
        user: JSON.parse(localStorage.getItem('userData') || '{}'),
        evidencias: [],
        groupedData: [],
        currentPage: 1,
        totalPages: 1,
        viewMode: localStorage.getItem('viewMode') || 'grid', // 'grid', 'list', 'details'
        filters: {
            orden: '',
            tipo: '',
            fechaInicio: '',
            fechaFin: ''
        }
    };

    // Check authentication
    if (!state.token) {
        window.location.href = 'login.html';
        return;
    }

    // DOM Elements
    const userInfo = document.getElementById('user-info');
    const logoutBtn = document.getElementById('logout-btn');
    const searchOrden = document.getElementById('search-orden');
    const filterTipo = document.getElementById('filter-tipo');
    const filterFechaInicio = document.getElementById('filter-fecha-inicio');
    const filterFechaFin = document.getElementById('filter-fecha-fin');
    const clearFiltersBtn = document.getElementById('clear-filters-btn');
    const evidenciasGrid = document.getElementById('evidencias-grid');
    const prevPageBtn = document.getElementById('prev-page');
    const nextPageBtn = document.getElementById('next-page');
    const pageInfo = document.getElementById('page-info');
    const viewButtons = document.querySelectorAll('.view-btn');

    // Initialize
    init();

    async function init() {
        displayUserInfo();
        setupEventListeners();
        setupViewControls();
        await loadStats();
        await loadEvidencias();
    }

    function displayUserInfo() {
        userInfo.textContent = `${state.user.email} (${state.user.rol})`;
    }

    function setupViewControls() {
        viewButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const view = btn.dataset.view;
                changeView(view);
            });

            // Set active state
            if (btn.dataset.view === state.viewMode) {
                btn.classList.add('active');
            }
        });
    }

    function changeView(viewMode) {
        state.viewMode = viewMode;
        localStorage.setItem('viewMode', viewMode);

        // Update button states
        viewButtons.forEach(btn => {
            btn.classList.toggle('active', btn.dataset.view === viewMode);
        });

        // Re-render with new view
        renderEvidencias();
    }

    function setupEventListeners() {
        logoutBtn.addEventListener('click', logout);

        searchOrden.addEventListener('input', debounce(() => {
            state.filters.orden = searchOrden.value.trim();
            state.currentPage = 1;
            loadEvidencias();
        }, 500));

        filterTipo.addEventListener('change', () => {
            state.filters.tipo = filterTipo.value;
            state.currentPage = 1;
            loadEvidencias();
        });

        filterFechaInicio.addEventListener('change', () => {
            state.filters.fechaInicio = filterFechaInicio.value;
            state.currentPage = 1;
            loadEvidencias();
        });

        filterFechaFin.addEventListener('change', () => {
            state.filters.fechaFin = filterFechaFin.value;
            state.currentPage = 1;
            loadEvidencias();
        });

        clearFiltersBtn.addEventListener('click', () => {
            searchOrden.value = '';
            filterTipo.value = '';
            filterFechaInicio.value = '';
            filterFechaFin.value = '';
            state.filters = { orden: '', tipo: '', fechaInicio: '', fechaFin: '' };
            state.currentPage = 1;
            loadEvidencias();
        });

        prevPageBtn.addEventListener('click', () => {
            if (state.currentPage > 1) {
                state.currentPage--;
                loadEvidencias();
            }
        });

        nextPageBtn.addEventListener('click', () => {
            if (state.currentPage < state.totalPages) {
                state.currentPage++;
                loadEvidencias();
            }
        });
    }

    async function loadStats() {
        // TODO: Create dedicated stats endpoint
        // For now, use evidencias data
    }

    async function loadEvidencias() {
        showLoading();

        try {
            const params = new URLSearchParams({
                page: state.currentPage,
                limit: 100,
                group_by: 'orden' // Always group by orden
            });

            if (state.filters.orden) params.append('orden', state.filters.orden);
            if (state.filters.tipo) params.append('tipo', state.filters.tipo);
            if (state.filters.fechaInicio) params.append('fecha_inicio', state.filters.fechaInicio);
            if (state.filters.fechaFin) params.append('fecha_fin', state.filters.fechaFin);

            const response = await api(`/api/evidencias/read.php?${params}`);

            // === DEBUGGING TEMPORAL ===
            console.log('=== API RESPONSE DEBUG ===');
            console.log('Response:', response);
            console.log('Success:', response?.success);
            console.log('Grouped:', response?.grouped);
            console.log('Data type:', typeof response?.data);
            console.log('Data:', response?.data);
            if (response?.data && response.data.length > 0) {
                console.log('First item:', response.data[0]);
                console.log('Has evidencias?:', response.data[0]?.evidencias);
            }
            console.log('=========================');
            // === FIN DEBUG ===

            if (response && response.success) {
                if (response.grouped) {
                    state.groupedData = response.data;
                    state.evidencias = flattenGroupedData(response.data);
                } else {
                    state.evidencias = response.data;
                    state.groupedData = [];
                }

                state.totalPages = response.pagination.pages;

                renderEvidencias();
                updatePagination(response.pagination);
                updateStats(response.pagination.total, response.data.length);
            } else {
                showError('Error al cargar evidencias');
            }
        } catch (error) {
            console.error('Load evidencias error:', error);
            showError('Error de conexión');
        }
    }

    function flattenGroupedData(groupedData) {
        const flat = [];
        groupedData.forEach(group => {
            flat.push(...group.evidencias);
        });
        return flat;
    }

    function renderEvidencias() {
        if (state.viewMode === 'list') {
            renderListView();
        } else if (state.viewMode === 'details') {
            renderDetailsView();
        } else {
            renderGridView();
        }
    }

    function renderGridView() {
        if (state.groupedData.length === 0) {
            evidenciasGrid.innerHTML = `
                <div class="loading-state">
                    <p>No se encontraron evidencias</p>
                </div>
            `;
            return;
        }

        evidenciasGrid.innerHTML = state.groupedData.map(group => `
            <div class="evidence-group">
                <div class="group-header" onclick="toggleGroup(this)">
                    <div class="group-info">
                        <span class="group-orden">#${group.orden_numero}</span>
                        <span class="group-stats">
                            ${group.total_imagenes} 📷 
                            ${group.total_videos} 🎥 
                            • ${group.total_size_formatted}
                        </span>
                    </div>
                    <div class="group-toggle">
                        <span class="toggle-icon">▼</span>
                    </div>
                </div>
                <div class="group-content" data-orden="${group.orden_numero}">
                    <div class="evidencias-grid">
                        ${group.evidencias.map(ev => `
                            <div class="evidence-item" data-id="${ev.id}">
                                <div class="evidence-thumbnail-wrapper" onclick="viewEvidence(${ev.id})">
                                    <img src="${ev.thumbnail_url || ev.archivo_url}" 
                                         alt="Evidencia" 
                                         class="evidence-thumbnail"
                                         loading="lazy">
                                    ${ev.archivo_tipo === 'video' ? '<div class="video-badge">🎥</div>' : ''}
                                </div>
                                
                                <div class="evidence-info">
                                    <div class="evidence-filename" title="${ev.archivo_nombre_original}">
                                        ${truncate(ev.archivo_nombre_original, 20)}
                                    </div>
                                    <div class="evidence-meta">
                                        <span>📅 ${formatDate(ev.fecha_creacion)}</span>
                                        <span>💾 ${ev.archivo_size_formatted}</span>
                                    </div>
                                    <button class="btn btn-secondary btn-sm btn-block" onclick="showActions(${ev.id}, '${ev.orden_numero}')">
                                        ⚙️ Acciones
                                    </button>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            </div>
        `).join('');
    }

    function renderListView() {
        if (state.evidencias.length === 0) {
            evidenciasGrid.innerHTML = `
                <div class="loading-state">
                    <p>No se encontraron evidencias</p>
                </div>
            `;
            return;
        }

        evidenciasGrid.innerHTML = `
            <table class="evidence-table">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Orden</th>
                        <th>Archivo</th>
                        <th>Fecha</th>
                        <th>Tamaño</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    ${state.evidencias.map(ev => `
                        <tr data-id="${ev.id}">
                            <td>
                                <span class="type-badge badge-${ev.archivo_tipo}">
                                    ${ev.archivo_tipo === 'video' ? '🎥' : '📷'}
                                </span>
                            </td>
                            <td><strong>#${ev.orden_numero}</strong></td>
                            <td>
                                <a href="#" onclick="viewEvidence(${ev.id}); return false;" class="file-link">
                                    ${truncate(ev.archivo_nombre_original, 30)}
                                </a>
                            </td>
                            <td>${formatDate(ev.fecha_creacion)}</td>
                            <td>${ev.archivo_size_formatted}</td>
                            <td>
                                <button class="btn btn-secondary btn-sm" onclick="showActions(${ev.id}, '${ev.orden_numero}')">
                                    ⚙️
                                </button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    }

    function renderDetailsView() {
        if (state.evidencias.length === 0) {
            evidenciasGrid.innerHTML = `
                <div class="loading-state">
                    <p>No se encontraron evidencias</p>
                </div>
            `;
            return;
        }

        evidenciasGrid.innerHTML = `
            <table class="evidence-table evidence-table-details">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Orden</th>
                        <th>Archivo</th>
                        <th>Fecha</th>
                        <th>Tamaño</th>
                        <th>Subido por</th>
                        <th>Usuario Telegram</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    ${state.evidencias.map(ev => `
                        <tr data-id="${ev.id}">
                            <td>
                                <span class="type-badge badge-${ev.archivo_tipo}">
                                    ${ev.archivo_tipo === 'video' ? '🎥 Video' : '📷 Imagen'}
                                </span>
                            </td>
                            <td><strong>#${ev.orden_numero}</strong></td>
                            <td>
                                <a href="#" onclick="viewEvidence(${ev.id}); return false;" class="file-link">
                                    ${ev.archivo_nombre_original}
                                </a>
                            </td>
                            <td>${formatDate(ev.fecha_creacion)}</td>
                            <td>${ev.archivo_size_formatted}</td>
                            <td>${ev.subido_por_email || '-'}</td>
                            <td>${ev.telegram_username || '-'}</td>
                            <td>
                                <button class="btn btn-secondary btn-sm" onclick="showActions(${ev.id}, '${ev.orden_numero}')">
                                    ⚙️ Acciones
                                </button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    }

    window.toggleGroup = function (header) {
        const group = header.parentElement;
        const content = group.querySelector('.group-content');
        const icon = header.querySelector('.toggle-icon');

        group.classList.toggle('collapsed');

        if (group.classList.contains('collapsed')) {
            content.style.display = 'none';
            icon.textContent = '▶';
        } else {
            content.style.display = 'block';
            icon.textContent = '▼';
        }
    };

    function updatePagination(pagination) {
        pageInfo.textContent = `Página ${pagination.page} de ${pagination.pages}`;
        prevPageBtn.disabled = pagination.page === 1;
        nextPageBtn.disabled = pagination.page === pagination.pages;

        document.getElementById('results-count').textContent =
            `${pagination.total} evidencia${pagination.total !== 1 ? 's' : ''}`;
    }

    function updateStats(totalEvidencias, totalOrdenes) {
        document.getElementById('total-evidencias').textContent = totalEvidencias;
        document.getElementById('ordenes-activas').textContent = totalOrdenes;
        // TODO: Implement other stats
    }

    window.viewEvidence = function (evidenciaId) {
        const ev = state.evidencias.find(e => e.id === evidenciaId);
        if (!ev) return;

        const modal = document.getElementById('view-modal');
        const modalBody = document.getElementById('modal-body');

        if (ev.archivo_tipo === 'video') {
            modalBody.innerHTML = `
                <video src="${ev.archivo_url}" controls autoplay style="max-width: 100%; max-height: 80vh;">
                    Tu navegador no soporta video.
                </video>
            `;
        } else {
            modalBody.innerHTML = `
                <img src="${ev.archivo_url}" alt="Evidencia" style="max-width: 100%; max-height: 80vh;">
            `;
        }

        modal.classList.remove('hidden');
    };

    window.showActions = async function (evidenciaId, ordenNumero) {
        const modal = document.getElementById('actions-modal');
        const modalTitle = document.getElementById('actions-modal-title');
        const modalBody = document.getElementById('actions-modal-body');

        modalTitle.textContent = `Acciones - Orden #${ordenNumero}`;

        modalBody.innerHTML = `
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <button class="btn btn-primary btn-block" onclick="generateGallery('${ordenNumero}')">
                    🔗 Generar Enlace de Galería
                </button>
                <button class="btn btn-secondary btn-block" onclick="resendNotification('${ordenNumero}')">
                    📤 Reenviar Notificación
                </button>
                <button class="btn btn-danger btn-block" onclick="deleteEvidence(${evidenciaId})">
                    🗑️ Eliminar Evidencia
                </button>
            </div>
        `;

        modal.classList.remove('hidden');
    };

    window.generateGallery = async function (ordenNumero) {
        try {
            const response = await api('/api/galeria/generate_token.php', {
                method: 'POST',
                body: JSON.stringify({ orden_numero: ordenNumero })
            });

            if (response && response.success) {
                const url = response.data.url;

                // Copy to clipboard
                navigator.clipboard.writeText(url).then(() => {
                    alert(`✅ Enlace generado y copiado:\n\n${url}\n\nVálido por ${response.data.expira_dias} días`);
                });

                closeModals();
            } else {
                alert('Error al generar enlace: ' + (response.error || 'Error desconocido'));
            }
        } catch (error) {
            console.error('Generate gallery error:', error);
            alert('Error de conexión');
        }
    };

    window.resendNotification = async function (ordenNumero) {
        const canal = prompt('¿Por qué canal enviar?\n\nwhatsapp / email', 'whatsapp');
        if (!canal) return;

        try {
            const response = await api('/api/evidencias/resend_notification.php', {
                method: 'POST',
                body: JSON.stringify({ orden_numero: ordenNumero, canal })
            });

            if (response && response.success) {
                alert('✅ Notificación enviada exitosamente');
                closeModals();
            } else {
                alert('⚠️ ' + (response.message || response.error || 'Error al enviar'));
            }
        } catch (error) {
            console.error('Resend error:', error);
            alert('Error de conexión');
        }
    };

    window.deleteEvidence = async function (evidenciaId) {
        if (!confirm('¿Estás seguro de eliminar esta evidencia?')) return;

        // TODO: Implement delete endpoint
        alert('Funcionalidad en desarrollo');
    };

    function closeModals() {
        document.getElementById('view-modal').classList.add('hidden');
        document.getElementById('actions-modal').classList.add('hidden');
    }

    // Close modals on overlay click
    document.getElementById('modal-overlay').addEventListener('click', closeModals);
    document.getElementById('actions-modal-overlay').addEventListener('click', closeModals);
    document.getElementById('modal-close').addEventListener('click', closeModals);
    document.getElementById('actions-modal-close').addEventListener('click', closeModals);

    function showLoading() {
        evidenciasGrid.innerHTML = `
            <div class="loading-state">
                <div class="spinner"></div>
                <p>Cargando evidencias...</p>
            </div>
        `;
    }

    function showError(message) {
        evidenciasGrid.innerHTML = `
            <div class="loading-state">
                <p style="color: var(--danger-color);">❌ ${message}</p>
            </div>
        `;
    }

    function logout() {
        if (confirm('¿Cerrar sesión?')) {
            localStorage.removeItem('authToken');
            localStorage.removeItem('userData');
            localStorage.removeItem('viewMode');
            window.location.href = 'login.html';
        }
    }

    async function api(url, options = {}) {
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${state.token}`
            }
        };

        const response = await fetch(url, { ...defaultOptions, ...options });

        if (response.status === 401) {
            alert('Sesión expirada. Por favor inicia sesión de nuevo.');
            logout();
            return null;
        }

        return response.json();
    }

    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('es-MX', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function truncate(str, length) {
        if (str.length <= length) return str;
        return str.substring(0, length) + '...';
    }

    function debounce(func, wait) {
        let timeout;
        return function (...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }
})();
