document.addEventListener('DOMContentLoaded', checkAdminSession);

        async function checkAdminSession() {
            try {
                const response = await fetch('../api/auth/verify_admin_session.php');
                if (!response.ok) { window.location.href = 'login.html'; return; }
                loadAllUsers();
                setupTabNavigation();
            } catch (error) { window.location.href = 'login.html'; }
        }

        async function loadAllUsers() {
            const tables = {
                pending: document.getElementById('pending-users-table'),
                approved: document.getElementById('approved-users-table'),
                rejected: document.getElementById('rejected-users-table'),
            };
            const loadingRow = (cols) => `<tr><td colspan="${cols}" class="px-6 py-4 text-center">Chargement...</td></tr>`;
            tables.pending.innerHTML = loadingRow(4);
            tables.approved.innerHTML = loadingRow(4);
            tables.rejected.innerHTML = loadingRow(4);

            try {
                const response = await fetch('../api/admin/get_all_users.php');
                const result = await response.json();

                if (result.success) {
                    const pending = result.data.filter(u => u.status === 'pending');
                    const approved = result.data.filter(u => u.status === 'approved');
                    const rejected = result.data.filter(u => u.status === 'rejected');
                    
                    updateStatsAndBadges(pending, approved, rejected);
                    renderPendingTable(pending);
                    renderApprovedTable(approved);
                    renderRejectedTable(rejected);
                } else { throw new Error(result.message); }
            } catch (error) {
                const errorRow = `<tr><td colspan="4" class="px-6 py-4 text-center text-red-500">Erreur: ${error.message}</td></tr>`;
                Object.values(tables).forEach(t => t.innerHTML = errorRow);
            }
        }

        function updateStatsAndBadges(pending, approved, rejected) {
            document.getElementById('stat-pending').textContent = pending.length;
            document.getElementById('stat-approved').textContent = approved.length;
            document.getElementById('stat-rejected').textContent = rejected.length;
            document.getElementById('stat-total').textContent = pending.length + approved.length + rejected.length;
            
            document.getElementById('badge-pending').textContent = pending.length;
            document.getElementById('badge-approved').textContent = approved.length;
            document.getElementById('badge-rejected').textContent = rejected.length;
        }

        function renderPendingTable(users) {
            const tableBody = document.getElementById('pending-users-table');
            if (users.length === 0) { tableBody.innerHTML = '<tr><td colspan="4" class="empty-state"><i class="fas fa-inbox"></i><p>Aucune demande en attente.</p></td></tr>'; return; }
            tableBody.innerHTML = '';
            users.forEach(user => {
                const row = `
                    <tr class="hover:bg-gray-50">
                        <td class="font-medium">${user.nom_complet}</td><td>${user.email}</td><td>${user.nom_etablissement}</td>
                        <td class="text-center"><div class="flex justify-center space-x-2">
                            <button onclick="updateUserStatus(${user.id}, 'approved')" class="btn btn-success"><i class="fas fa-check mr-1"></i>Approuver</button>
                            <button onclick="updateUserStatus(${user.id}, 'rejected')" class="btn btn-danger"><i class="fas fa-times mr-1"></i>Rejeter</button>
                        </div></td>
                    </tr>`;
                tableBody.innerHTML += row;
            });
        }
        
        function renderApprovedTable(users) {
             const tableBody = document.getElementById('approved-users-table');
             if (users.length === 0) { tableBody.innerHTML = '<tr><td colspan="4" class="empty-state"><i class="fas fa-user-check"></i><p>Aucun directeur approuvé.</p></td></tr>'; return; }
             tableBody.innerHTML = '';
             users.forEach(user => {
                const row = `
                    <tr>
                        <td class="font-medium">${user.nom_complet}</td><td>${user.email}</td><td>${user.nom_etablissement}</td>
                        <td class="text-center"><span class="status-badge badge-approved"><i class="fas fa-check-circle mr-1"></i>Approuvé</span></td>
                    </tr>`;
                tableBody.innerHTML += row;
             });
        }

        function renderRejectedTable(users) {
            const tableBody = document.getElementById('rejected-users-table');
            if (users.length === 0) { tableBody.innerHTML = '<tr><td colspan="4" class="empty-state"><i class="fas fa-user-times"></i><p>Aucune inscription rejetée.</p></td></tr>'; return; }
            tableBody.innerHTML = '';
            users.forEach(user => {
                const row = `
                     <tr>
                        <td class="font-medium">${user.nom_complet}</td><td>${user.email}</td><td>${user.nom_etablissement}</td>
                        <td class="text-center"><div class="flex justify-center space-x-2">
                            <button onclick="updateUserStatus(${user.id}, 'approved')" class="btn btn-success"><i class="fas fa-redo-alt mr-1"></i>Ré-approuver</button>
                            <button onclick="updateUserStatus(${user.id}, 'deleted')" class="btn btn-outline"><i class="fas fa-trash-alt mr-1"></i>Supprimer</button>
                        </div></td>
                    </tr>`;
                tableBody.innerHTML += row;
            });
        }
        
        async function updateUserStatus(userId, status) {
            const actions = { 'approved': 'approuver', 'rejected': 'rejeter', 'deleted': 'supprimer définitivement' };
            if (!confirm(`Êtes-vous sûr de vouloir ${actions[status]} cet utilisateur ?`)) return;
            try {
                const response = await fetch('../api/admin/update_user_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: userId, new_status: status })
                });
                const result = await response.json();
                if (result.success) {
                    alert(`Utilisateur ${status === 'deleted' ? 'supprimé' : actions[status]} avec succès.`);
                    loadAllUsers();
                } else {
                    alert("Erreur: " + (result.message || "Impossible de mettre à jour le statut."));
                }
            } catch (error) { alert("Une erreur de communication est survenue."); }
        }
        
        function setupTabNavigation() {
            const tabsContainer = document.getElementById('admin-tabs');
            tabsContainer.addEventListener('click', (e) => {
                const targetButton = e.target.closest('.tab-button');
                if (targetButton) {
                    tabsContainer.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
                    targetButton.classList.add('active');
                    const targetId = targetButton.dataset.target;
                    document.querySelectorAll('.tab-content').forEach(panel => {
                        panel.classList.toggle('active', panel.id === targetId);
                    });
                }
            });
        }