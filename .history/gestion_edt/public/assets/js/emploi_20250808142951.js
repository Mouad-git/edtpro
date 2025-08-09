const jours = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
    const seances = ['S1', 'S2', 'S3', 'S4'];
    const SEANCE_DURATION = 2.5;
    let autoGenConstraints = {};
    const dayClasses = {
      'Lundi': 'lundi-cell',
      'Mardi': 'mardi-cell',
      'Mercredi': 'mercredi-cell',
      'Jeudi': 'jeudi-cell',
      'Vendredi': 'vendredi-cell',
      'Samedi': 'samedi-cell'
    };
    let appData = { formateurs: [], groupes: [], fusionGroupes: [], espaces: [], affectations: [] };
    let timetableData = {};
    let groupHours = {};
    let clipboard = { type: null, data: [] };
    let selectedDate = new Date();
    let calendarCurrentDate = new Date();
    let currentWeekValue = '';
    let groupHoursChart = null; // Gardera en mémoire notre instance de graphique
    
    
    Chart.register(ChartDataLabels);
    

    

    function updateEspacesTags() {
        const container = document.getElementById('espacesContainer');
        if (!container) return;
        container.innerHTML = '';
        appData.espaces.forEach(espace => {
            const tag = document.createElement('div');
            tag.className = 'espace-tag';
            tag.innerHTML = `${espace}<button onclick="removeEspace('${espace}')">×</button>`;
            container.appendChild(tag);
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('notificationClose').addEventListener('click', () => {
        const notification = document.getElementById('notification');
        notification.classList.remove('show'); // <-- CORRECTION : On retire la classe 'show'
        notification.classList.add('hide');
    });
});
    // =========================================================================
    // ==     NOUVELLE SECTION : LOGIQUE DU CALENDRIER PERSONNALISÉ           ==
    // =========================================================================

    /**
     * Initialise les écouteurs d'événements pour le calendrier personnalisé.
     */
    function setupCustomCalendar() {
        const display = document.getElementById('customWeekDisplay');
        const popup = document.getElementById('calendarPopup');
        const prevBtn = document.getElementById('prevMonthBtn');
        const nextBtn = document.getElementById('nextMonthBtn');

        display.addEventListener('click', () => {
            const isVisible = popup.style.display === 'block';
            popup.style.display = isVisible ? 'none' : 'block';
            if (!isVisible) {
                calendarCurrentDate = new Date(selectedDate);
                generateCalendar(calendarCurrentDate);
            }
        });

        prevBtn.addEventListener('click', () => {
            calendarCurrentDate.setMonth(calendarCurrentDate.getMonth() - 1);
            generateCalendar(calendarCurrentDate);
        });

        nextBtn.addEventListener('click', () => {
            calendarCurrentDate.setMonth(calendarCurrentDate.getMonth() + 1);
            generateCalendar(calendarCurrentDate);
        });
        
        // Fermer le popup si on clique en dehors
        document.addEventListener('click', (e) => {
            if (!display.contains(e.target) && !popup.contains(e.target)) {
                popup.style.display = 'none';
            }
        });
    }
    
    /**
     * Génère et affiche le calendrier pour un mois donné.
     * @param {Date} date - Une date dans le mois à afficher.
     */
    function generateCalendar(date) {
        const calendarBody = document.getElementById('calendarBody');
        const calendarTitle = document.getElementById('calendarTitle');
        
        calendarBody.innerHTML = '';
        const month = date.getMonth();
        const year = date.getFullYear();

        const monthName = date.toLocaleDateString('fr-FR', { month: 'long' });
        calendarTitle.textContent = `${monthName.charAt(0).toUpperCase() + monthName.slice(1)} ${year}`;

        const firstDayOfMonth = new Date(year, month, 1);
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const dateOffset = (firstDayOfMonth.getDay() + 6) % 7; 

        const selectedWeekInfo = getWeekInfo(selectedDate);
        const { startOfWeek, endOfWeek } = selectedWeekInfo;

        let currentDay = 1;
        for (let i = 0; i < 6; i++) {
            const row = document.createElement('tr');
            for (let j = 0; j < 7; j++) {
                const cell = document.createElement('td');
                if (i === 0 && j < dateOffset) {
                    // Cellules vides avant
                } else if (currentDay > daysInMonth) {
                    // Cellules vides après
                } else {
                    const cellDate = new Date(year, month, currentDay);
                    cellDate.setHours(0,0,0,0);
                    const dayDiv = document.createElement('div');
                    dayDiv.className = 'calendar-day';
                    dayDiv.textContent = currentDay;

                    const today = new Date();
                    today.setHours(0,0,0,0);
                    if (cellDate.getTime() === today.getTime()) {
                        dayDiv.classList.add('today');
                    }
                    
                    if (cellDate.getMonth() === 7) { // Août
                        dayDiv.classList.add('disabled-day');
                    } else {
                        dayDiv.onclick = () => {
                           updateWeekSelection(cellDate);
                           document.getElementById('calendarPopup').style.display = 'none';
                        };
                    }
                    
                    // NOUVELLE LOGIQUE POUR LE STYLE DE LA SEMAINE SÉLECTIONNÉE
                    if (cellDate >= startOfWeek && cellDate <= endOfWeek) {
                        if (cellDate.getTime() === startOfWeek.getTime()) {
                            dayDiv.classList.add('selected-start');
                        } else if (cellDate.getTime() === endOfWeek.getTime()) {
                            dayDiv.classList.add('selected-end');
                        } else {
                            dayDiv.classList.add('selected-in-range');
                        }
                    }

                    cell.appendChild(dayDiv);
                    currentDay++;
                }
                row.appendChild(cell);
            }
            calendarBody.appendChild(row);
            if (currentDay > daysInMonth) break;
        }
    }
    
    /**
     * Met à jour l'affichage et charge les données pour la semaine d'une date donnée.
     * @param {Date} date - La date sélectionnée.
     */
    function updateWeekSelection(date) {
        selectedDate = date;
        const weekInfo = getWeekInfo(date);
        
        const display = document.getElementById('customWeekDisplay');
        const start = weekInfo.startOfWeek.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' });
        const end = weekInfo.endOfWeek.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' });
        
        document.getElementById('weekDisplayText').innerHTML = `<strong>S${weekInfo.weekNumber}</strong> <span style="font-size:0.9em; color: #555;">(${start} - ${end})</span>`;
        
        if (weekInfo.weekValue !== currentWeekValue) {
            currentWeekValue = weekInfo.weekValue;
            loadWeekData(currentWeekValue);
        }

        generateCalendar(calendarCurrentDate);
    }
    
    /**
     * Obtient l'année de début de l'année scolaire pour une date donnée.
     * @param {Date} date - La date à évaluer.
     * @returns {number} - L'année de début (ex: 2024 pour l'année 2024-2025).
     */
    function getSchoolYear(date) {
        return (date.getMonth() < 8) ? date.getFullYear() - 1 : date.getFullYear(); // 8 = Septembre
    }
    
    /**
     * Fonction principale qui calcule toutes les informations sur une semaine scolaire.
     * @param {Date} date - La date pour laquelle on veut les informations.
     * @returns {object} - Un objet contenant les détails de la semaine.
     */
    function getWeekInfo(date) {
        const schoolYear = getSchoolYear(date);
        
        let firstDayOfSeptember = new Date(schoolYear, 8, 1);
        let dayOfWeek = firstDayOfSeptember.getDay();
        let firstMonday = new Date(firstDayOfSeptember);
        if (dayOfWeek !== 1) {
            const offset = (dayOfWeek === 0) ? 1 : 8 - dayOfWeek;
            firstMonday.setDate(firstDayOfSeptember.getDate() + offset);
        }
        
        let startOfWeek = new Date(date);
        const day = startOfWeek.getDay();
        const diff = startOfWeek.getDate() - day + (day === 0 ? -6 : 1);
        startOfWeek.setDate(diff);
        startOfWeek.setHours(0, 0, 0, 0);
        firstMonday.setHours(0, 0, 0, 0);
        
        let weekNumber = 1;
        if (startOfWeek >= firstMonday) {
            const timeDiff = startOfWeek.getTime() - firstMonday.getTime();
            weekNumber = Math.floor(timeDiff / (1000 * 60 * 60 * 24 * 7)) + 1;
        }

        const endOfWeek = new Date(startOfWeek);
        endOfWeek.setDate(startOfWeek.getDate() + 6);
        
        return {
            schoolYear: schoolYear,
            weekNumber: weekNumber,
            startOfWeek: startOfWeek,
            endOfWeek: endOfWeek,
            weekValue: `${schoolYear}-W${weekNumber}`
        };
    }

    // =========================================================================
    // ==               FIN DE LA SECTION CALENDRIER                          ==
    // =========================================================================


    window.addEventListener('resize', () => {
        document.querySelectorAll('.auto-resize-select').forEach(resizeTextToFit);
    });

    function resizeTextToFit(element) { // Renommé de 'select' à 'element'
    const cell = element.closest('td');
    if (!cell) return;
    element.style.fontSize = '10px';
    let fontSize = 10;
    while (element.scrollWidth > cell.offsetWidth && fontSize > 6) {
        fontSize -= 0.5;
        element.style.fontSize = fontSize + 'px';
    }
}

    function getFormattedName(name) {
        if (!name) return '';
        const words = name.trim().split(/\s+/).filter(word => word.length > 0);
        if (words.length <= 1) return name;
        if (words.length === 2) return words[1];
        const avantDernier = words[words.length - 2];
        const dernier = words[words.length - 1];
        return avantDernier.length < 4 ? `${avantDernier} ${dernier}` : dernier;
    }

    function handleFileUpload(e) {
        const file = e.target.files[0];
        if (!file) return;
        document.getElementById('fileName').textContent = file.name;
        document.getElementById('fileName').style.display = 'block';
        const formData = new FormData();
        formData.append('excelFile', file);
        document.getElementById('weekLoader').style.display = 'flex';
        fetch('../api/data/upload_base_data.php', { method: 'POST', body: formData })
            .then(response => {
                if (!response.ok) {
                    // Capture les erreurs HTTP comme 500, 404 etc.
                    return response.text().then(text => { 
                        // On essaie de ne pas afficher tout le HTML d'erreur dans l'alerte
                        const shortError = text.length > 300 ? text.substring(0, 300) + '...' : text;
                        throw new Error(`Erreur serveur (HTTP ${response.status}): ${shortError}`);
                    });
                }
                return response.json(); // Tente de parser en JSON
            })
            .then(result => {
                document.getElementById('weekLoader').style.display = 'none';
                if (result.success) {
                    showNotification("Fichier traité et données mises à jour !", "success");
                    loadInitialData(); // On recharge tout pour refléter les changements
                } else {
                    showNotification(result.message || "Erreur lors du traitement.", "error");
                }
            })
            .catch(error => {
                document.getElementById('weekLoader').style.display = 'none';
                console.error("Erreur détaillée de l'upload:", error);
                // Affiche une erreur plus informative
                if (error instanceof SyntaxError) {
                    showNotification("Erreur: La réponse du serveur n'est pas un JSON valide. Vérifiez les logs PHP.", "error");
                } else {
                    showNotification(error.message || "Erreur de communication lors de l'envoi.", "error");
                }
            });
    }

    function generateTimetable() {
      const tbody = document.querySelector('#timetable tbody');
      tbody.innerHTML = '';
      if (!appData.formateurs || appData.formateurs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="26" style="text-align:center; padding: 20px; font-size: 1.1rem; color: #777;">Aucun formateur à afficher. Veuillez charger un fichier Excel contenant les données de base.</td></tr>';
        return;
      }
      renderTimetableFromData(timetableData);
      const timetableElement = document.getElementById('timetable');
      timetableElement.removeEventListener('change', handleTableInteraction);
      timetableElement.addEventListener('change', handleTableInteraction);
    }

    function handleTableInteraction(e) {
        const target = e.target;
        if (target.tagName !== 'SELECT') return;
        const type = target.dataset.type;
        if (type === 'groupe') {
            handleGroupeChange(e);
            updateFormateurCell(target.dataset.formateur);
        } else if (type === 'module') {
            handleModuleChange(e);
        } else if (type === 'salle') {
            handleSalleChange(e);
        }
        const cell = target.closest('td');
        if (target.value) {
            cell.classList.add('selected-cell');
        } else {
            cell.classList.remove('selected-cell');
        }
        resizeTextToFit(target);
    }

    function createSelect(jour, seance, formateur, type, options, selectedValue = '') {
        const select = document.createElement('select');
        select.dataset.jour = jour;
        select.dataset.seance = seance;
        select.dataset.formateur = formateur;
        select.dataset.type = type;
        select.className = "auto-resize-select";
        const emptyOption = document.createElement('option');
        emptyOption.value = '';
        emptyOption.textContent = '';
        select.appendChild(emptyOption);
        options.forEach(option => {
            const opt = document.createElement('option');
            opt.value = option;
            opt.textContent = option;
            if (option === selectedValue) {
                opt.selected = true;
            }
            select.appendChild(opt);
        });
        if (type === 'module') {
            select.disabled = !selectedValue;
        }
        return select;
    }


    function updateGroupeSelectOptions(formateur, jour, seance, salle, isTeams) {
        const groupeSelect = document.querySelector(
            `select[data-formateur="${formateur}"][data-jour="${jour}"][data-seance="${seance}"][data-type="groupe"]`
        );
        if (!groupeSelect) return;
        const currentGroupe = groupeSelect.value;
        groupeSelect.innerHTML = '';
        const emptyOption = document.createElement('option');
        emptyOption.value = '';
        groupeSelect.appendChild(emptyOption);
        const source = isTeams ? appData.fusionGroupes : appData.groupes;
        const typeAffection = isTeams ? 'synchrone' : 'presentiel';
        let foundCurrent = false;
        source.forEach(groupe => {
            if (appData.affectations.some(a => a.formateur === formateur && a.groupe === groupe && a.type === typeAffection)) {
                const option = document.createElement('option');
                option.value = groupe;
                option.textContent = groupe;
                groupeSelect.appendChild(option);
                if (groupe === currentGroupe) {
                    foundCurrent = true;
                }
            }
        });
        if (foundCurrent) {
            groupeSelect.value = currentGroupe;
        } else if (currentGroupe) {
            const option = document.createElement('option');
            option.value = currentGroupe;
            option.textContent = currentGroupe;
            option.style.color = 'red';
            groupeSelect.appendChild(option);
            groupeSelect.value = currentGroupe;
        }
    }

// Remplacez votre fonction handleGroupeChange par celle-ci

async function handleGroupeChange(e) {
    const select = e.target;
    const jour = select.dataset.jour;
    const seance = select.dataset.seance;
    const formateur = select.dataset.formateur;
    const groupe = select.value;

    // --- DÉCLARATION UNIQUE ET VÉRIFICATION DE CONFLIT ---
    const conflict = checkGroupConflict(jour, seance, groupe, select);
    if (conflict) {
        showNotification(`Conflit: Le groupe ${groupe} est déjà pris.`, "chevauchement");
        highlightConflict(jour, seance, 'groupe', groupe);
        select.value = '';
        // On met à jour les données et on déclenche un change pour que les modules se vident
        updateTimetableData(jour, seance, formateur, 'groupe', '');
        select.dispatchEvent(new Event('change'));
        return;
    }

    // --- MISE À JOUR DES DONNÉES ET DES HEURES ---
    const oldGroupe = timetableData[formateur][jour][seance].groupe;
    const moduleSelect = document.querySelector(`select[data-formateur="${formateur}"][data-jour="${jour}"][data-seance="${seance}"][data-type="module"]`);

    if (groupe) {
        if (oldGroupe && oldGroupe !== groupe) {
            groupHours[oldGroupe] = (groupHours[oldGroupe] || 0) - SEANCE_DURATION;
            checkGroupHours(oldGroupe);
        }
        groupHours[groupe] = (groupHours[groupe] || 0) + SEANCE_DURATION;
        checkGroupHours(groupe);
    } else {
        if (oldGroupe) {
            groupHours[oldGroupe] = (groupHours[oldGroupe] || 0) - SEANCE_DURATION;
            checkGroupHours(oldGroupe);
        }
    }
    
    updateTimetableData(jour, seance, formateur, 'groupe', groupe);
    updateModuleOptions(formateur, jour, seance, groupe);
    updateFormateurCell(formateur); // Mettre à jour les heures du formateur

    // La logique pour les séances de 5h a été retirée ici.
}

    /**
 * Copie les données d'une séance (groupe, module, salle) vers une autre séance adjacente
 * et met à jour l'interface utilisateur.
 * @param {string} formateur - Le formateur concerné.
 * @param {string} jour - Le jour concerné.
 * @param {string} sourceSeance - La séance d'origine (ex: 'S1').
 * @param {string} targetSeance - La séance de destination (ex: 'S2').
 */
function copySessionData(formateur, jour, sourceSeance, targetSeance) {
    // 1. Récupérer les données de la séance source
    const sourceSession = timetableData[formateur][jour][sourceSeance];
    
    // 2. Mettre à jour le modèle de données pour la séance cible
    timetableData[formateur][jour][targetSeance] = { ...sourceSession };
    
    // 3. Mettre à jour l'interface pour la séance cible
    // On trouve les 3 <select> de la colonne cible
    const targetSelects = {
        groupe: document.querySelector(`select[data-formateur="${formateur}"][data-jour="${jour}"][data-seance="${targetSeance}"][data-type="groupe"]`),
        module: document.querySelector(`select[data-formateur="${formateur}"][data-jour="${jour}"][data-seance="${targetSeance}"][data-type="module"]`),
        salle: document.querySelector(`select[data-formateur="${formateur}"][data-jour="${jour}"][data-seance="${targetSeance}"][data-type="salle"]`)
    };

    if (targetSelects.groupe) {
        targetSelects.groupe.value = sourceSession.groupe;
        targetSelects.groupe.dispatchEvent(new Event('change')); // Déclenche la mise à jour des modules
    }

    // On utilise un petit délai pour s'assurer que les options du module sont à jour avant de le sélectionner
    setTimeout(() => {
        if (targetSelects.module) {
            targetSelects.module.value = sourceSession.module;
            targetSelects.module.dispatchEvent(new Event('change'));
        }
        if (targetSelects.salle) {
            targetSelects.salle.value = sourceSession.salle;
            targetSelects.salle.dispatchEvent(new Event('change'));
        }
        // Mettre à jour le comptage des heures
        updateFormateurCell(formateur);
    }, 50); // Un délai de 50ms est généralement suffisant
}
    
    function checkGroupHours(groupe) {
        if (!groupe) return;
        const hours = groupHours[groupe] || 0;
        updateGroupCells(groupe);
        if (hours >= 40) {
            showNotification(`Le groupe ${groupe} a atteint ${hours.toFixed(1)}h.`, "warning");
            document.querySelectorAll(`select[data-type="groupe"] option[value="${groupe}"]:checked`).forEach(opt => {
                const cell = opt.closest('td');
                cell.classList.add('group-highlight');
                setTimeout(() => cell.classList.remove('group-highlight'), 3000);
            });
        }
    }
    
    function updateGroupCells(groupe) {
      if (!groupe) return;
      const hours = groupHours[groupe] || 0;
      document.querySelectorAll(`select[data-type="groupe"] option[value="${groupe}"]:checked`).forEach(opt => {
            const cell = opt.closest('td');
            if(cell){
                cell.classList.toggle('group-highlight', hours >= 40);
            }
        });
    }

    function getCurrentTimetableData() {
        return {
            semaine: currentWeekValue, // Utilise la variable globale du calendrier
            emploiDuTemps: timetableData
        };
    }

    function updateFormateurCell(formateur) {
      const formateurCell = document.querySelector(`td.formateur-cell[data-formateur="${formateur}"]`);
      if (!formateurCell) return;
      let count = 0;
      if (timetableData[formateur]) {
        for (const jour in timetableData[formateur]) {
            for (const seance in timetableData[formateur][jour]) {
                if (timetableData[formateur][jour][seance].groupe) {
                    count++;
                }
            }
        }
      }
      const heures = count * SEANCE_DURATION;
      const hoursElement = document.getElementById(`hours-${formateur}`);
      if (hoursElement) {
        hoursElement.textContent = `${heures.toFixed(1)}`;
      }
      formateurCell.classList.remove('formateur-green', 'formateur-orange', 'formateur-red');
      if (heures <= 22.5) {
        formateurCell.classList.add('formateur-green');
      } else if (heures <= 35) {
        formateurCell.classList.add('formateur-orange');
      } else {
        formateurCell.classList.add('formateur-red');
      }
    }

    function handleModuleChange(e) {
    const select = e.target;
    const jour = select.dataset.jour;
    const seance = select.dataset.seance;
    const formateur = select.dataset.formateur;
    const module = select.value; // La valeur est propre, sans étoile

    // --- NOUVELLE LIGNE : Mettre à jour le span visible ---
    const displaySpan = select.parentElement.querySelector('.custom-select-display');
    if (displaySpan) {
        displaySpan.textContent = module;
    }

    updateTimetableData(jour, seance, formateur, 'module', module);

    const cell = select.closest('td');
    if (cell) {
        cell.classList.remove('module-s1', 'module-s2', 'module-annual', 'module-regional');

        if (module) {
            const selectedOption = select.options[select.selectedIndex];
            const semester = selectedOption.dataset.semester;
            const isRegional = selectedOption.dataset.regional === 'true';

            if (semester) {
                cell.classList.add(`module-${semester.toLowerCase()}`);
            }

            if (isRegional) {
                cell.classList.add('module-regional');
            }

            cell.classList.add('selected-cell');
        } else {
            cell.classList.remove('selected-cell');
        }
    }
}


    function handleSalleChange(e) {
        const select = e.target;
        const jour = select.dataset.jour;
        const seance = select.dataset.seance;
        const formateur = select.dataset.formateur;
        const salle = select.value;
        const cell = select.closest('td');
        cell.classList.toggle('selected-cell', !!salle);
        if (salle && checkRoomConflict(jour, seance, salle, select)) {
            showNotification(`La salle ${salle} est déjà occupée.`, "chevauchement");
            highlightConflict(jour, seance, 'salle', salle);
            select.value = '';
            cell.classList.remove('selected-cell');
            updateTimetableData(jour, seance, formateur, 'salle', '');
            return;
        }
        const isTeams = salle === "TEAMS";
        updateGroupeSelectOptions(formateur, jour, seance, salle, isTeams);
        const groupeSelect = document.querySelector(`select[data-jour="${jour}"][data-seance="${seance}"][data-formateur="${formateur}"][data-type="groupe"]`);
        if (groupeSelect) {
            const options = Array.from(groupeSelect.options).map(opt => opt.value);
            if (!options.includes(groupeSelect.value)) {
                groupeSelect.value = '';
                groupeSelect.dispatchEvent(new Event('change'));
            }
        }
        updateTimetableData(jour, seance, formateur, 'salle', salle);
    }

    function checkGroupConflict(jour, seance, groupe, currentSelect) {
        if (!groupe) return false;
        const newGroups = groupe.trim().split(/\s+/).filter(g => g);
        const allGroupeSelects = document.querySelectorAll(`select[data-jour="${jour}"][data-seance="${seance}"][data-type="groupe"]`);
        for (const select of allGroupeSelects) {
            if (select === currentSelect || !select.value) continue;
            const existingGroups = select.value.trim().split(/\s+/).filter(g => g);
            if (newGroups.some(ng => existingGroups.includes(ng))) {
                return true;
            }
        }
        return false;
    }

    function checkRoomConflict(jour, seance, salle, currentSelect) {
      if (salle === "TEAMS" || !salle) return false;
      const allSalleSelects = document.querySelectorAll(`select[data-jour="${jour}"][data-seance="${seance}"][data-type="salle"]`);
      for (const select of allSalleSelects) {
        if (select !== currentSelect && select.value === salle) {
            return true;
        }
      }
      return false;
    }

    function highlightConflict(jour, seance, type, value) {
      document.querySelectorAll(`select[data-jour="${jour}"][data-seance="${seance}"][data-type="${type}"]`).forEach(select => {
        const values = select.value.trim().split(/\s+/);
        if (values.includes(value)) {
            const cell = select.closest('td');
            cell.classList.add('conflict-highlight');
            setTimeout(() => cell.classList.remove('conflict-highlight'), 3000);
        }
      });
    }

    function initializeTimetableData() {
        timetableData = {};
        appData.formateurs.forEach(formateur => {
            timetableData[formateur] = {};
            jours.forEach(jour => {
                timetableData[formateur][jour] = {};
                seances.forEach(seance => {
                    timetableData[formateur][jour][seance] = { groupe: '', module: '', salle: '' };
                });
            });
        });
        groupHours = {};
    }

    function updateTimetableData(jour, seance, formateur, type, value) {
      if (timetableData[formateur] &&
          timetableData[formateur][jour] &&
          timetableData[formateur][jour][seance]) {
        timetableData[formateur][jour][seance][type] = value;
      }
    }

    async function saveTimetable() {
        const semaine = currentWeekValue;
        if (!semaine) {
            showNotification("Veuillez sélectionner une semaine.", "warning");
            return;
        }
        const dataToSave = getCurrentTimetableData();
        try {
            const saveResponse = await fetch('../api/data/save_timetable.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(dataToSave)
            });
            const saveResult = await saveResponse.json();
            if (saveResult.success) {
                showNotification("Emploi du temps enregistré avec succès", "success");
            } else {
                showNotification("Erreur: " + (saveResult.message || 'Erreur serveur'), "error");
            }
        } catch (error) {
            showNotification("Erreur réseau: " + error.message, "error");
        }
    }

    document.getElementById('saveTimetableBtn')?.addEventListener('click', saveTimetable);

    function resetTimetable() {
      if (confirm("Êtes-vous sûr de vouloir effacer l'emploi du temps affiché ?")) {
        initializeTimetableData();
        renderTimetableFromData(timetableData);
        showNotification("L'emploi du temps a été effacé.", "info");
      }
    }

        // =========================================================================
    // ==           NOUVELLE FONCTION DE NOTIFICATION (CORRIGÉE)            ==
    // =========================================================================
    let notificationTimeout;
    function showNotification(message, type = "info") {
        const notification = document.getElementById('notification');
        const header = document.getElementById('notificationHeader');
        const text = document.getElementById('notificationText');
        const iconContainer = document.getElementById('notificationIcon');
        
        // Annuler le timeout précédent pour éviter une fermeture prématurée
        clearTimeout(notificationTimeout);

        // --- CORRECTION CLÉ : Réinitialiser l'état en retirant les deux classes ---
        notification.classList.remove('show', 'hide');

        // Définir les styles et le contenu en fonction du type
        const typeSettings = {
            success: { header: 'Succès', icon: 'fa-check', color: '#4CAF50' },
            error: { header: 'Erreur', icon: 'fa-times', color: '#f44336' },
            chevauchement:{ header: 'Chevauchement', icon: 'fa-exclamation-triangle', color: '#f44336' },
            warning: { header: 'Attention', icon: 'fa-exclamation-triangle', color: '#ff9800' },
            info: { header: 'Information', icon: 'fa-info-circle', color: '#2196F3' }
        };
        const settings = typeSettings[type] || typeSettings.info;

        header.textContent = settings.header;
        text.textContent = message;
        iconContainer.innerHTML = `<i class="fas ${settings.icon}"></i>`;
        notification.style.setProperty('--accent-color', settings.color);

        // Forcer un reflow pour que l'animation puisse se relancer
        void notification.offsetWidth;

        // Afficher la notification
        notification.classList.add('show');

        // La cacher après un délai
        notificationTimeout = setTimeout(() => {
            notification.classList.remove('show'); // <-- On retire 'show'
            notification.classList.add('hide');    // <-- On ajoute 'hide'
        }, 4000); // 4 secondes
    }
    
    function setupExcelSelection() {
      const table = document.getElementById('timetable');
      const selectionDiv = document.createElement('div');
      selectionDiv.className = 'excel-selection';
      selectionDiv.style.display = 'none';
      document.body.appendChild(selectionDiv);
      let isSelecting = false;
      let startCell = null;
      let startX, startY;
      table.addEventListener('mousedown', function(e) {
        if (!e.ctrlKey) {
          clearExcelSelection();
          return;
        }
        const cell = e.target.closest('td');
        if (!cell) return;
        if (cell.classList.contains('formateur-cell') || 
            cell.classList.contains('type-cell')) {
          return;
        }
        e.preventDefault();
        isSelecting = true;
        startCell = cell;
        startX = e.clientX;
        startY = e.clientY;
        const toolbar = document.getElementById('excelToolbar');
        toolbar.classList.add('visible');
        const rect = cell.getBoundingClientRect();
        selectionDiv.style.left = `${rect.left}px`;
        selectionDiv.style.top = `${rect.top}px`;
        selectionDiv.style.width = `${rect.width}px`;
        selectionDiv.style.height = `${rect.height}px`;
        selectionDiv.style.display = 'block';
        document.querySelectorAll('.excel-selected').forEach(c => {
          c.classList.remove('excel-selected');
        });
        cell.classList.add('excel-selected');
      });
      document.addEventListener('mousemove', function(e) {
        if (!isSelecting || !startCell) return;
        const currentCell = document.elementFromPoint(e.clientX, e.clientY)?.closest('td');
        if (!currentCell || currentCell === startCell) return;
        const startRect = startCell.getBoundingClientRect();
        const currentRect = currentCell.getBoundingClientRect();
        const minX = Math.min(startRect.left, currentRect.left);
        const maxX = Math.max(startRect.right, currentRect.right);
        const minY = Math.min(startRect.top, currentRect.top);
        const maxY = Math.max(startRect.bottom, currentRect.bottom);
        selectionDiv.style.left = `${minX}px`;
        selectionDiv.style.top = `${minY}px`;
        selectionDiv.style.width = `${maxX - minX}px`;
        selectionDiv.style.height = `${maxY - minY}px`;
        const allCells = document.querySelectorAll('tbody td');
        allCells.forEach(cell => {
          if (cell.classList.contains('formateur-cell') || 
              cell.classList.contains('type-cell')) {
            return;
          }
          const cellRect = cell.getBoundingClientRect();
          const isInSelection = 
            cellRect.left >= minX && 
            cellRect.right <= maxX && 
            cellRect.top >= minY && 
            cellRect.bottom <= maxY;
          if (isInSelection) {
            cell.classList.add('excel-selected');
          } else {
            cell.classList.remove('excel-selected');
          }
        });
      });
      document.addEventListener('mouseup', function() {
        isSelecting = false;
      });
      table.addEventListener('contextmenu', function(e) {
        if (isSelecting) {
          e.preventDefault();
        }
      });
    }

    function setupExcelToolbarListeners() {
      document.getElementById('copyBtn').addEventListener('click', copySelectedCells);
      document.getElementById('pasteBtn').addEventListener('click', pasteSelectedCells);
      document.getElementById('cutBtn').addEventListener('click', cutSelectedCells);
      document.getElementById('deleteBtn').addEventListener('click', deleteSelectedCells);
      document.getElementById('clearSelectionBtn').addEventListener('click', clearExcelSelection);
      document.addEventListener('keydown', function(e) {
        if (e.ctrlKey) {
          if (e.key === 'c') {
            e.preventDefault();
            copySelectedCells();
          } else if (e.key === 'v') {
            e.preventDefault();
            pasteSelectedCells();
          } else if (e.key === 'x') {
            e.preventDefault();
            cutSelectedCells();
          }
        } else if (e.key === 'Delete') {
          e.preventDefault();
          deleteSelectedCells();
        }
      });
      document.getElementById('clearSelectionBtn').addEventListener('click', clearExcelSelection);
    }
    
    function clearExcelSelection() {
      document.querySelectorAll('.excel-selected').forEach(c => {
        c.classList.remove('excel-selected');
      });
      const selectionDiv = document.querySelector('.excel-selection');
      if (selectionDiv) {
        selectionDiv.style.display = 'none';
      }
      const toolbar = document.getElementById('excelToolbar');
      toolbar.classList.remove('visible');
    }

    function showPasteNotification(message) {
      const notification = document.getElementById('pasteNotification');
      notification.textContent = message;
      notification.classList.add('show');
      setTimeout(() => notification.classList.remove('show'), 2000);
    }
    
    function copySelectedCells() {
      const selectedCells = document.querySelectorAll('.excel-selected');
      if (selectedCells.length === 0) {
        showNotification("Aucune cellule sélectionnée", "warning");
        return;
      }
      clipboard = {
        type: 'copy',
        data: [],
        matrix: []
      };
      const rows = {};
      selectedCells.forEach(cell => {
        const rowIndex = cell.closest('tr').rowIndex;
        const cellIndex = cell.cellIndex;
        if (!rows[rowIndex]) rows[rowIndex] = {};
        rows[rowIndex][cellIndex] = cell;
      });
      const rowIndices = Object.keys(rows).sort((a, b) => a - b);
      rowIndices.forEach(rowIndex => {
        const row = [];
        const cellIndices = Object.keys(rows[rowIndex]).sort((a, b) => a - b);
        cellIndices.forEach(cellIndex => {
          const cell = rows[rowIndex][cellIndex];
          const select = cell.querySelector('select');
          if (select) {
            row.push({
              formateur: select.dataset.formateur,
              jour: select.dataset.jour,
              seance: select.dataset.seance,
              type: select.dataset.type,
              value: select.value
            });
          }
        });
        clipboard.matrix.push(row);
      });
      showPasteNotification(`${selectedCells.length} cellules copiées`);
    }
    
    function pasteSelectedCells() {
      if (!clipboard || !clipboard.matrix || clipboard.matrix.length === 0) {
        showNotification("Aucune donnée à coller", "warning");
        return;
      }
      const selectedCells = document.querySelectorAll('.excel-selected');
      if (selectedCells.length === 0) {
        showNotification("Aucune cellule sélectionnée pour le collage", "warning");
        return;
      }
      const destMatrix = [];
      const destRows = {};
      selectedCells.forEach(cell => {
        const rowIndex = cell.closest('tr').rowIndex;
        const cellIndex = cell.cellIndex;
        if (!destRows[rowIndex]) destRows[rowIndex] = {};
        destRows[rowIndex][cellIndex] = cell;
      });
      const rowIndices = Object.keys(destRows).sort((a, b) => a - b);
      rowIndices.forEach(rowIndex => {
        const row = [];
        const cellIndices = Object.keys(destRows[rowIndex]).sort((a, b) => a - b);
        cellIndices.forEach(cellIndex => {
          row.push(destRows[rowIndex][cellIndex]);
        });
        destMatrix.push(row);
      });
      let pasteCount = 0;
      for (let i = 0; i < destMatrix.length; i++) {
        for (let j = 0; j < destMatrix[i].length; j++) {
          const srcRow = i % clipboard.matrix.length;
          const srcCol = j % clipboard.matrix[0].length;
          const cellData = clipboard.matrix[srcRow][srcCol];
          const cell = destMatrix[i][j];
          const select = cell.querySelector('select');
          if (select && select.dataset.type === cellData.type) {
            select.value = cellData.value;
            const event = new Event('change');
            select.dispatchEvent(event);
            updateTimetableData(
              select.dataset.jour,
              select.dataset.seance,
              select.dataset.formateur,
              select.dataset.type,
              cellData.value
            );
            const cellElement = select.closest('td');
            if (cellData.value) {
              cellElement.classList.add('selected-cell');
              cellElement.classList.remove('empty-cell');
            } else {
              cellElement.classList.remove('selected-cell');
              cellElement.classList.add('empty-cell');
            }
            if (select.dataset.type === 'groupe') {
              updateModuleOptions(
                select.dataset.formateur,
                select.dataset.jour,
                select.dataset.seance,
                cellData.value
              );
            }
            pasteCount++;
          }
        }
      }
      if (pasteCount > 0) {
        showPasteNotification(`${pasteCount} cellules collées`);
      } else {
        showNotification("Aucune cellule compatible pour le collage", "warning");
      }
    }
    
    function cutSelectedCells() {
      copySelectedCells();
      const selectedCells = document.querySelectorAll('.excel-selected');
      selectedCells.forEach(cell => {
        const selects = cell.querySelectorAll('select');
        selects.forEach(select => {
          select.value = '';
          const event = new Event('change');
          select.dispatchEvent(event);
          updateTimetableData(
            select.dataset.jour,
            select.dataset.seance,
            select.dataset.formateur,
            select.dataset.type,
            ''
          );
          const cellElement = select.closest('td');
          cellElement.classList.remove('selected-cell');
          cellElement.classList.add('empty-cell');
        });
      });
      showPasteNotification("Cellules coupées");
    }

    function deleteSelectedCells() {
      const selectedCells = document.querySelectorAll('.excel-selected');
      if (selectedCells.length === 0) {
        showNotification("Aucune cellule sélectionnée", "warning");
        return;
      }
      let deleteCount = 0;
      selectedCells.forEach(cell => {
        const selects = cell.querySelectorAll('select');
        selects.forEach(select => {
          select.value = '';
          const event = new Event('change');
          select.dispatchEvent(event);
          updateTimetableData(
            select.dataset.jour,
            select.dataset.seance,
            select.dataset.formateur,
            select.dataset.type,
            ''
          );
          const cellElement = select.closest('td');
          cellElement.classList.remove('selected-cell');
          cellElement.classList.add('empty-cell');
          deleteCount++;
        });
      });
      showPasteNotification(`${deleteCount} cellules effacées`);
    }

    function setupEventListeners() {
    const fileInput = document.getElementById('fileInput');
    const resetBtn = document.getElementById('resetBtn');
    const autoGenerateBtn = document.getElementById('autoGenerateBtn');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const runAutoGenerateBtn = document.getElementById('runAutoGenerateBtn');

    if (fileInput) fileInput.addEventListener('change', handleFileUpload);
    if (resetBtn) resetBtn.addEventListener('click', resetTimetable);
    if (autoGenerateBtn) autoGenerateBtn.addEventListener('click', openAutoGenModal);
    if (closeModalBtn) closeModalBtn.addEventListener('click', () => {
        document.getElementById('autoGenModal').style.display = 'none';
    });
    if (runAutoGenerateBtn) runAutoGenerateBtn.addEventListener('click', runAutoGeneration);

    const timetable = document.getElementById('timetable');
    if (timetable) {
        // Gère les actions *après* un changement.
        timetable.removeEventListener('change', handleTableInteraction);
        timetable.addEventListener('change', handleTableInteraction);

        // NOUVEAU : Gestion des clics pour mettre à jour l'affichage *avant* sélection.
        timetable.addEventListener('mousedown', function(e) {
            if (e.target.tagName !== 'SELECT') return;

            // Si c'est un menu de salle, on filtre les options.
            if (e.target.dataset.type === 'salle') {
                updateSalleOptions(e.target);
            } 
            // Si c'est un menu de groupe, on colore les options indisponibles.
            else if (e.target.dataset.type === 'groupe') {
                styleGroupOptions(e.target);
            }
        });
    }

    // NOUVEL ÉCOUTEUR POUR LE BOUTON D'IMPORTATION
    document.getElementById('importWeekBtn')?.addEventListener('click', showWeekDropdown);
      
      // Fermer le menu quand on clique en dehors
      document.addEventListener('click', (e) => {
        const menu = document.getElementById('weekImportMenu');
        const btn = document.getElementById('importWeekBtn');
        
        if (menu.classList.contains('show') && 
            !menu.contains(e.target) && 
            !btn.contains(e.target)) {
          menu.classList.remove('show');
        }
      });

      // =========================================================
    // == NOUVEL ÉCOUTEUR POUR LE BOUTON D'ANALYSE DES HEURES ==
    // =========================================================
    const analyzeBtn = document.getElementById('analyzeHoursBtn');
    if (analyzeBtn) {
        analyzeBtn.addEventListener('click', () => {
            // Vérification pour s'assurer qu'il y a des données à analyser
            if (!timetableData || Object.keys(timetableData).length === 0) {
                showNotification("Aucun emploi du temps à analyser. Veuillez charger ou générer des données.", "warning");
                return;
            }
            
            // On appelle simplement la fonction existante avec les données actuelles
            calculateAndShowGroupHours(timetableData);
        });
    }

      // À l'intérieur de la fonction setupEventListeners() ou simplement dans le scope global du script

document.getElementById('closeGroupHoursModal').addEventListener('click', () => {
    document.getElementById('groupHoursModal').style.display = 'none';
});

// Bonus : fermer la modale si on clique en dehors
window.addEventListener('click', (event) => {
    const modal = document.getElementById('groupHoursModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
});
}
    
function loadWeekData(weekValue) {
        const loader = document.getElementById('weekLoader');
        loader.style.display = 'flex';
        fetch(`../api/data/get_timetable.php?semaine=${weekValue}`)
            .then(response => response.json())
            .then(result => {
                loader.style.display = 'none';
                if (result.success) {
                    timetableData = result.data;
                    renderTimetableFromData(timetableData);
                    showNotification("Emploi du temps chargé", "success");
                } else {
                    initializeTimetableData();
                    renderTimetableFromData(timetableData);
                    showNotification("Aucun EDT trouvé pour cette semaine, tableau initialisé.", "info");
                }
            })
            .catch(error => {
                loader.style.display = 'none';
                showNotification("Erreur réseau: " + error.message, "error");
            });
    }

    /**
 * [CORRIGÉ ET ROBUSTE] Détermine si un module est régional.
 * Gère plusieurs formats de données pour la propriété 'est_regional' (1, "1", true, "true").
 * @param {string} formateur - Le nom du formateur.
 * @param {string} groupe - Le nom du ou des groupes.
 * @param {string} module - Le nom du module.
 * @returns {boolean} - True si le module est marqué comme régional, false sinon.
 */
function isModuleRegional(formateur, groupe, module) {
    if (!formateur || !groupe || !module) {
        return false;
    }

    const subGroups = groupe.trim().split(/\s+/).filter(Boolean);

    for (const subGroup of subGroups) {
        const affectation = appData.affectations.find(a =>
            a.formateur === formateur &&
            a.groupe === subGroup &&
            a.module === module
        );

        if (affectation && affectation.hasOwnProperty('est_regional')) {
            const value = affectation.est_regional;
            // Cette nouvelle condition est plus flexible et gère plusieurs cas :
            // true (booléen), 1 (nombre), "1" (chaîne), "true" (chaîne, insensible à la casse)
            return value === true || value == 1 || String(value).toLowerCase() === 'true';
        }
    }
    // Si aucune affectation n'est trouvée ou si la propriété manque.
    return false;
}


/**
 * [CORRIGÉ] Met à jour les options du menu déroulant des modules.
 * Utilise la nouvelle logique robuste pour attacher les métadonnées correctes.
 */
/**
 * [MISE À JOUR] Met à jour les options du menu déroulant des modules.
 * Ajoute une étoile (⭐) pour identifier les modules régionaux directement dans la liste.
 */
 function updateModuleOptions(formateur, jour, seance, groupe) {
  const moduleSelect = document.querySelector(`select[data-formateur="${formateur}"][data-jour="${jour}"][data-seance="${seance}"][data-type="module"]`);
  if (!moduleSelect) {
    return;
  }

  const currentModule = moduleSelect.value;
  moduleSelect.innerHTML = '<option value=""></option>';

  if (!groupe) {
    moduleSelect.disabled = true;
    handleModuleChange({ target: moduleSelect });
    return;
  }

  moduleSelect.disabled = false;
  const subGroups = groupe.trim().split(/\s+/).filter(Boolean);
  const modulesEtStatut = new Map();

  appData.affectations.forEach(affectation => {
    if (affectation.formateur === formateur && subGroups.includes(affectation.groupe)) {
      const moduleName = affectation.module;
      const semesterStatus = getModuleSemester(formateur, affectation.groupe, moduleName);
      const estRegional = isModuleRegional(formateur, affectation.groupe, moduleName);
      
      modulesEtStatut.set(moduleName, { semesterStatus, estRegional });
    }
  });

  modulesEtStatut.forEach((info, moduleName) => {
    const option = document.createElement('option');
    option.value = moduleName;
    
    // ====================================================================
    // ==   MODIFICATION : Ajout de l'étoile pour les modules régionaux  ==
    // ====================================================================
    let displayText = moduleName;
    if (info.estRegional) {
      displayText = '⭐ ' + moduleName;
    }
    option.textContent = displayText;
    // ====================================================================
    
    option.dataset.semester = info.semesterStatus;
    option.dataset.regional = info.estRegional ? 'true' : 'false';

    if (moduleName === currentModule) {
      option.selected = true;
    }
    moduleSelect.appendChild(option);
  });
  
  if (currentModule && !modulesEtStatut.has(currentModule)) {
      moduleSelect.value = '';
  }

  const event = new Event('change', { bubbles: true });
  moduleSelect.dispatchEvent(event);
}

/**
 * [CORRIGÉ] Génère et affiche le contenu HTML de l'emploi du temps.
 * Utilise la nouvelle logique robuste pour appliquer la classe `module-regional`.
 */
function renderTimetableFromData(data) {
    const tbody = document.querySelector('#timetable tbody');
    let html = '';
    if (!appData.formateurs || appData.formateurs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="26" style="text-align:center; padding: 20px;">Données de base non chargées. Veuillez importer un fichier Excel.</td></tr>';
        return;
    }

    initializeTimetableData();
    if (data) {
        timetableData = JSON.parse(JSON.stringify(data));
    }

    groupHours = {};
    for (const formateur in timetableData) {
        for (const jour in timetableData[formateur]) {
            for (const seance in timetableData[formateur][jour]) {
                const session = timetableData[formateur][jour][seance];
                if (session.groupe) {
                    const subGroups = session.groupe.trim().split(/\s+/).filter(g => g);
                    subGroups.forEach(g => {
                       groupHours[g] = (groupHours[g] || 0) + SEANCE_DURATION;
                    });
                }
            }
        }
    }

    appData.formateurs.forEach(formateur => {
        const formateurData = timetableData[formateur] || {};
        let seanceCount = 0;
        if(formateurData){
            Object.values(formateurData).forEach(day => {
                Object.values(day).forEach(session => {
                    if (session.groupe) seanceCount++;
                });
            });
        }
        const heures = seanceCount * SEANCE_DURATION;
        let formateurClass = 'formateur-green';
        if (heures > 35) formateurClass = 'formateur-red';
        else if (heures > 22.5) formateurClass = 'formateur-orange';

        html += `<tr>
          <td rowspan="3" class="formateur-cell ${formateurClass}" data-formateur="${formateur}">
            <div class="formateur-content">
              <div class="formateur-name">${formateur}</div>
              <div class="formateur-hours" id="hours-${formateur}">${heures.toFixed(1)}</div>
            </div>
          </td>
          <td class="type-cell">Groupe</td>`;
        
        jours.forEach(jour => {
            seances.forEach(seance => {
                const session = formateurData[jour]?.[seance] || { groupe: '', module: '', salle: '' };
                const dayClass = dayClasses[jour];
                const isSelected = session.groupe ? 'selected-cell' : '';
                const isGroupOverloaded = session.groupe && groupHours[session.groupe] >= 40 ? 'group-highlight' : '';
                const isTeams = session.salle === 'TEAMS';
                const groupesPossibles = isTeams ? appData.fusionGroupes : appData.groupes;
                const typeAffection = isTeams ? 'synchrone' : 'presentiel';
                const groupesFiltres = groupesPossibles.filter(g => appData.affectations.some(a => a.formateur === formateur && a.groupe === g && a.type === typeAffection));
                html += `<td class="auto-resize-cell draggable-cell ${dayClass} ${isSelected} ${isGroupOverloaded}" draggable="true" data-type="groupe" data-jour="${jour}" data-seance="${seance}" data-formateur="${formateur}">
                  <select class="auto-resize-select" data-type="groupe" data-jour="${jour}" data-seance="${seance}" data-formateur="${formateur}">
                    <option value=""></option>
                    ${groupesFiltres.map(g => `<option value="${g}" ${g === session.groupe ? 'selected' : ''}>${g}</option>`).join('')}
                  </select>
                  <div class="drag-indicator"><i class="fas fa-arrows-alt"></i></div>
                </td>`;
            });
        });
        html += `</tr>`;
        
        html += `<tr><td class="type-cell">Module</td>`;
        jours.forEach(jour => {
            seances.forEach(seance => {
                const session = formateurData[jour]?.[seance] || { groupe: '', module: '', salle: '' };
                const dayClass = dayClasses[jour];
                const isModuleSelected = session.module ? 'selected-cell' : '';

                const semester = getModuleSemester(formateur, session.groupe, session.module);
                // Appel à la fonction corrigée pour déterminer si la classe doit être ajoutée
                const isRegional = isModuleRegional(formateur, session.groupe, session.module);
                const semesterClass = semester ? `module-${semester.toLowerCase()}` : '';
                const regionalClass = isRegional ? 'module-regional' : '';

                const modulesPossibles = new Map();
                if (session.groupe) {
                    const subGroups = session.groupe.trim().split(/\s+/).filter(Boolean);
                    appData.affectations.forEach(a => {
                        if (a.formateur === formateur && subGroups.includes(a.groupe)) {
                            const modSemester = getModuleSemester(formateur, a.groupe, a.module);
                            const modIsRegional = isModuleRegional(formateur, a.groupe, a.module);
                            if (modSemester) {
                                modulesPossibles.set(a.module, { semester: modSemester, isRegional: modIsRegional });
                            }
                        }
                    });
                }
                
                let moduleOptionsHTML = '';
                modulesPossibles.forEach((meta, mod) => {
                    moduleOptionsHTML += `<option value="${mod}" data-semester="${meta.semester}" data-regional="${meta.isRegional}" ${mod === session.module ? 'selected' : ''}>${mod}</option>`;
                });
                
                // La classe ${regionalClass} est maintenant ajoutée directement ici
                html += `<td class="auto-resize-cell ${dayClass} ${isModuleSelected} ${semesterClass} ${regionalClass}" data-jour="${jour}" data-seance="${seance}" data-formateur="${formateur}">
  <div class="custom-select-wrapper">
    <span class="custom-select-display">${session.module || ''}</span>
    <select class="auto-resize-select" data-type="module" data-jour="${jour}" data-seance="${seance}" data-formateur="${formateur}" ${!session.groupe ? 'disabled' : ''}>
      <option value=""></option>
      ${moduleOptionsHTML}
    </select>
  </div>
</td>`;
            });
        });
        html += `</tr>`;

        html += `<tr class="salle-row"><td class="type-cell">Espace</td>`;
        jours.forEach(jour => {
            seances.forEach(seance => {
                const session = formateurData[jour]?.[seance] || { groupe: '', module: '', salle: '' };
                const dayClass = dayClasses[jour];
                const isSalleSelected = session.salle ? 'selected-cell' : '';
                html += `<td class="auto-resize-cell ${dayClass} ${isSalleSelected}" data-jour="${jour}" data-seance="${seance}" data-formateur="${formateur}">
                  <select class="auto-resize-select" data-type="salle" data-jour="${jour}" data-seance="${seance}" data-formateur="${formateur}">
                    <option value=""></option>
                    ${appData.espaces.map(e => `<option value="${e}" ${e === session.salle ? 'selected' : ''}>${e}</option>`).join('')}
                  </select>
                </td>`;
            });
        });
        html += `</tr>`;
    });
    
    tbody.innerHTML = html;
    document.querySelectorAll('.custom-select-wrapper').forEach(wrapper => {
        const select = wrapper.querySelector('select');
        const display = wrapper.querySelector('.custom-select-display');
        if (select && display) {
            display.textContent = select.value;
            // On appelle aussi resizeTextToFit sur le span pour un ajustement parfait
            resizeTextToFit(display); 
        }
    });
    document.querySelectorAll('.auto-resize-select').forEach(resizeTextToFit);
    Object.keys(groupHours).forEach(checkGroupHours);
    setupDragAndDrop();
}


    
    let dragSrcElement = null;
    function setupDragAndDrop() {
  const draggables = document.querySelectorAll('.draggable-cell');
  draggables.forEach(item => {
    item.addEventListener('dragstart', handleDragStart, false);
    // C'est cette ligne qui cause l'erreur car handleDragEnter n'est pas défini
    item.addEventListener('dragenter', handleDragEnter, false); 
    item.addEventListener('dragover', handleDragOver, false);
    item.addEventListener('dragleave', handleDragLeave, false);
    item.addEventListener('drop', handleDrop, false);
    item.addEventListener('dragend', handleDragEnd, false);
  });
}

    function handleDragEnter(e) {
  // 'this' est l'élément de la table sur lequel le curseur entre.
  // 'dragSrcElement' est l'élément que l'on est en train de glisser.
  
  // On vérifie que l'on ne dépose pas la séance sur un autre formateur.
  // Cette vérification ajoute de la robustesse.
  if (this.dataset.formateur === dragSrcElement.dataset.formateur) {
    this.classList.add('drop-target');
  }
}

    function handleDragStart(e) {
  dragSrcElement = this;
  const formateur = this.dataset.formateur;
  const jour = this.dataset.jour;
  const seance = this.dataset.seance;
  const sessionData = timetableData[formateur][jour][seance];

  // Si on essaie de glisser une cellule vide, on annule l'opération
  if (!sessionData || !sessionData.groupe) {
      e.preventDefault();
      return;
  }

  e.dataTransfer.effectAllowed = 'move';
  e.dataTransfer.setData('text/plain', JSON.stringify({ formateur, jour, seance }));
  this.classList.add('dragging');

  // --- NOUVELLE LOGIQUE POUR METTRE EN ÉVIDENCE LES EMPLACEMENTS POSSIBLES ---
  
  // On récupère toutes les cellules "groupe" du formateur concerné
  const allTrainerCells = document.querySelectorAll(`td[data-formateur="${formateur}"][data-type="groupe"]`);

  allTrainerCells.forEach(cell => {
    const targetJour = cell.dataset.jour;
    const targetSeance = cell.dataset.seance;
    
    // On ne se compare pas à soi-même
    if (targetJour === jour && targetSeance === seance) return;
    
    // On récupère la séance qui se trouve actuellement dans la cellule cible
    const targetOriginalSession = timetableData[formateur][targetJour][targetSeance];

    // Vérification en deux temps pour un ÉCHANGE (swap) :
    // 1. Est-ce que la séance glissée peut aller dans la cellule cible sans conflit externe ?
    const canSourceMoveToTarget = isSlotAvailableForSession(targetJour, targetSeance, sessionData, formateur);

    // 2. Est-ce que la séance de la cellule cible peut aller dans la cellule d'origine sans conflit externe ?
    const canTargetMoveToSource = isSlotAvailableForSession(jour, seance, targetOriginalSession, formateur);

    // Si les deux conditions sont vraies, alors l'échange est possible
    if (canSourceMoveToTarget && canTargetMoveToSource) {
       // On trouve les 3 cellules correspondantes (groupe, module, salle) pour ce créneau cible
       const correspondingCells = document.querySelectorAll(`td[data-formateur="${formateur}"][data-jour="${targetJour}"][data-seance="${targetSeance}"]`);
       correspondingCells.forEach(c => c.classList.add('drop-possible'));
    }
  });
}

    function handleDragOver(e) {
      if (e.preventDefault) {
        e.preventDefault();
      }
      e.dataTransfer.dropEffect = 'move';
      return false;
    }

    function handleDragEnd(e) {
  // On nettoie toutes les classes temporaires de toute la table
  document.querySelectorAll('.dragging, .drop-target, .drop-possible').forEach(function (item) {
    item.classList.remove('dragging', 'drop-target', 'drop-possible');
  });
}

    function handleDragLeave(e) {
      this.classList.remove('drop-target');
    }

    // --- FONCTION : handleDrop ---
function handleDrop(e) {
    e.stopPropagation();
    
    // Vérifier que l'élément source et l'élément cible sont différents
    if (dragSrcElement === this) {
        return false;
    }

    // Récupérer les informations de la source et de la cible
    const sourceInfo = JSON.parse(e.dataTransfer.getData('text/plain'));
    const srcFormateur = sourceInfo.formateur;
    const srcJour = sourceInfo.jour;
    const srcSeance = sourceInfo.seance;

    const targetCell = this; // 'this' est l'élément cible (où on dépose)
    const targetFormateur = targetCell.dataset.formateur;
    const targetJour = targetCell.dataset.jour;
    const targetSeance = targetCell.dataset.seance;

    // Vérification de sécurité : empêcher le déplacement entre formateurs
    if (srcFormateur !== targetFormateur) {
        showNotification("Déplacement non autorisé entre formateurs.", "warning");
        return false;
    }

    // Récupérer les sessions source et cible
    const sourceSession = JSON.parse(JSON.stringify(timetableData[srcFormateur][srcJour][srcSeance]));
    const targetSession = JSON.parse(JSON.stringify(timetableData[targetFormateur][targetJour][targetSeance]));

    // Créer un timetable temporaire pour tester les conflits
    let tempTimetable = JSON.parse(JSON.stringify(timetableData));
    tempTimetable[srcFormateur][srcJour][srcSeance] = targetSession;
    tempTimetable[targetFormateur][targetJour][targetSeance] = sourceSession;

    // Vérifier les conflits pour la session source (dans son nouvel emplacement)
    const sourceConflict = checkSessionConflict(srcJour, srcSeance, targetSession, tempTimetable, { formateur: srcFormateur });
    if (sourceConflict) {
        showNotification(`Déplacement impossible: ${sourceConflict}`, "chevauchement");
        return false;
    }

    // Vérifier les conflits pour la session cible (dans son nouvel emplacement)
    const targetConflict = checkSessionConflict(targetJour, targetSeance, sourceSession, tempTimetable, { formateur: targetFormateur });
    if (targetConflict) {
        showNotification(`Déplacement impossible: ${targetConflict}`, "chevauchement");
        return false;
    }

    // Si tout est OK, effectuer le swap dans le modèle de données principal
    timetableData[srcFormateur][srcJour][srcSeance] = targetSession;
    timetableData[targetFormateur][targetJour][targetSeance] = sourceSession;

    // Afficher un message de succès
    showNotification("Session déplacée avec succès.", "success");

    // --- ACTION CRUCIALE : Re-rendre tout le tableau ---
    // Cela recrée tous les éléments HTML, y compris les <select>.
    // Le post-traitement dans renderTimetableFromData s'occupera de déclencher
    // handleModuleChange pour appliquer les styles.
    renderTimetableFromData(timetableData);
    // --- FIN ACTION CRUCIALE ---

    // Nettoyer les classes CSS de drag-and-drop (géré aussi par handleDragEnd)
    document.querySelectorAll('.dragging, .drop-target, .drop-possible').forEach(function (item) {
        item.classList.remove('dragging', 'drop-target', 'drop-possible');
    });

    return false;
}
// --- FIN FONCTION : handleDrop ---

    function checkSessionConflict(jour, seance, sessionToCheck, timetable, exclusion) {
        if (!sessionToCheck || !sessionToCheck.groupe) return null;
        const groupesToCheck = sessionToCheck.groupe.trim().split(/\s+/).filter(Boolean);
        const salleToCheck = sessionToCheck.salle;
        for (const formateur in timetable) {
            if (formateur === exclusion.formateur) continue;
            const otherSession = timetable[formateur][jour][seance];
            if (salleToCheck && salleToCheck !== 'TEAMS' && otherSession.salle === salleToCheck) {
                return `Conflit de salle pour "${salleToCheck}" avec le formateur ${formateur}.`;
            }
            if (otherSession.groupe) {
                const autresGroupes = otherSession.groupe.trim().split(/\s+/).filter(Boolean);
                if (groupesToCheck.some(g => autresGroupes.includes(g))) {
                    return `Conflit de groupe ("${sessionToCheck.groupe}") avec le formateur ${formateur}.`;
                }
            }
        }
        return null;
    }

    function handleDragEnd(e) {
      document.querySelectorAll('.draggable-cell').forEach(function (item) {
        item.classList.remove('dragging', 'drop-target');
      });
    }

    let clipboardConfiguration = null;
    
    function openAutoGenModal() {
    const container = document.getElementById('autoGenConstraintsContainer');

    // 1. Détection des années de formation
    const anneesFormation = [...new Set(appData.groupes.map(getAnneeFormation))]
        .filter(annee => annee !== 'N/A')
        .sort();

    // ========================================================================
    // == MODIFICATION : Nouveau style minimaliste pour les boutons          ==
    // ========================================================================
    let tauxHtml = '';
    if (anneesFormation.length > 0) {
        tauxHtml = `
            <div style="margin-top: 0px;">
                <h4 style="margin-top:0; margin-bottom: 8px; color: #2c3e50; font-weight: 600;">
                    <i class="fas fa-percentage"></i> Répartition par Niveau
                </h4>
                <div id="tauxContainer" style="display: flex; flex-wrap: wrap; gap: 15px; align-items: center; padding: 8px; border-radius: 8px;">
                    ${anneesFormation.map(annee => `
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <label for="taux-${annee}" style="font-weight: 500; color: #34495e; min-width: auto;">
                                ${annee} :
                            </label>
                            <div style="position: relative;">
                                <input type="number" id="taux-${annee}" class="taux-input" min="0" max="100" 
                                       value="${annee === '1A' ? 100 : 0}" data-annee="${annee}" 
                                       style="width: 80px; height: 30px; padding: 9px 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 1rem; background: #ffffff; transition: all 0.3s;">
                                <span style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); font-weight: bold; color: #7f8c8d; pointer-events: none;">%</span>
                            </div>
                        </div>
                    `).join('')}
                    <div id="tauxTotal" style="margin-left: auto; text-align: right; font-weight: 700; font-size: 1.1rem; transition: color 0.3s; color: #27ae60;">
                      Total : <span id="tauxTotalValue">100</span>%
                    </div>
                </div>
            </div>
        `;
    }
        
    const bulkEditPanelHTML = `
        <div id="bulkEditPanel" style="padding: 15px; background-color: #f8fafc; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">

          
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 25px;">
                
                <!-- Colonne de gauche: Heures, TEAMS, Espaces -->
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <!-- Organisation horizontale -->
                    <div style="display: flex; gap: 20px; margin-bottom: 0px;">
                        <!-- Heures/Semaine -->
                        <div style="flex: 1;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">
                                <i class="fas fa-clock"></i> Heures/Semaine
                            </label>
                            <div style="display: flex; gap: 8px;">
                                <input type="number" id="bulkHours" value="25" step="2.5"
                                       style="flex: 1; height: 35px; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 1rem; background: #ffffff;">
                                <button data-action="apply-hours" class="btn-primary" style="padding: 10px 16px; height: 35px;">
                                    <i class="fas fa-check"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Sessions TEAMS -->
                        <div style="flex: 1;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">
                                <i class="fas fa-video"></i> Sessions TEAMS
                            </label>
                            <div style="display: flex; gap: 8px;">
                                <input type="number" id="bulkTeams" value="0" 
                                       style="flex: 1; height: 35px; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 1rem; background: #ffffff;">
                                <button data-action="apply-teams" class="btn-primary" style="padding: 10px 16px; height: 35px;">
                                    <i class="fas fa-check"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    

                    <div style="margin-top: 0px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">
                            <i class="fas fa-map-marker-alt"></i> Gérer les Espaces
                        </label>
                        <div style="display: flex; gap: 8px;">
                            <select id="bulkSpaceSelect" style="flex: 1; height: 35px; padding: 7px 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 1rem; background: #ffffff;">
                                ${appData.espaces.map(e => `<option value="${e}">${e}</option>`).join('')}
                            </select>
                            <button data-action="add-space" class="btn-success" style="padding: 10px 16px; height: 35px;">
                                <i class="fas fa-plus"></i>
                            </button>
                            <button data-action="remove-space" class="btn-danger" style="padding: 10px 16px; height: 35px;">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>

                    ${tauxHtml}

                    <h4 style="margin-top: 0px; margin-bottom: -8px; color: #2c3e50; font-weight: 600;">
                        <i class="fas fa-filter"></i> Forcer les Modules par Semestre
                    </h4>
                    <div id="semesterFilterContainer" style="display: flex; gap: 20px; align-items: center; padding: 5px; padding-left: 10px; border-radius: 8px;">
                        <label class="flex items-center gap-2 cursor-pointer" style="font-weight: 500;">
                            <input type="checkbox" id="forceS1" class="semester-checkbox h-5 w-5 rounded border-gray-300 text-blue-600" checked>
                            <span class="font-semibold" style="color: #3498db;">Semestre 1</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer" style="font-weight: 500;">
                            <input type="checkbox" id="forceS2" class="semester-checkbox h-5 w-5 rounded border-gray-300 text-green-600" checked>
                            <span class="font-semibold" style="color: #27ae60;">Semestre 2</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer" style="font-weight: 500;">
                            <input type="checkbox" id="forceAnnual" class="semester-checkbox h-5 w-5 rounded border-gray-300 text-orange-600" checked>
                            <span class="font-semibold" style="color: #e67e22;">Annuels</span>
                        </label>
                    </div>
                </div>

                <!-- Colonne de droite: Grille de sélection des indisponibilités -->
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">
                        <i class="fas fa-calendar-times"></i> Gérer les Indisponibilités
                    </label>
                    <div style="color: #7f8c8d; font-size: 0.9rem; margin-bottom: 10px;">
                        Cliquez-glissez pour sélectionner
                    </div>
                    <div style="display: flex; gap: 10px; margin-top: 5px;">
                        <!-- === GRILLE DE SÉLECTION INVERSÉE === -->
                        <table id="bulkAvailabilityGrid" class="availability-grid-style">
                            <thead>
                                <tr>
                                    <th></th> <!-- Coin vide -->
                                    ${seances.map(s => `<th style="padding: 8px 10px; background: #f8fafc;">${s}</th>`).join('')}
                                </tr>
                            </thead>
                            <tbody>
                                ${jours.map(j => `
                                    <tr>
                                        <th style="padding: 8px 10px; background: #f8fafc;">${j}</th>
                                        ${seances.map(s => `<td class="bulk-slot" data-jour="${j}" data-seance="${s}"></td>`).join('')}
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                        <div style="display: flex; flex-direction: column; gap: 8px; justify-content: center;">
                            <button data-action="block-availability" class="btn-danger" style="padding: 10px 16px;">
                                <i class="fas fa-ban"></i> Bloquer
                            </button>
                            <button data-action="unblock-availability" class="btn-success" style="padding: 10px 16px;">
                                <i class="fas fa-check-circle"></i> Débloquer
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px; padding-top: 10px; border-top: 1px solid #e2e8f0;">
            <div id="bulkSelectionInfo" style="font-weight: 500; color: #333; font-size: 1.05rem;">Aucun formateur sélectionné.</div>
            <button id="invertSelectionBtn" class="btn-secondary" style="padding: 8px 16px; width: 177px !important;">
              <i class="fas fa-exchange-alt"></i> Inverser la sélection
            </button>
        </div>
        </div>
        <style>
            /* NOUVEAU STYLE DES BOUTONS - MINIMALISTE */
            .btn-primary, .btn-secondary, .btn-success, .btn-danger {
                border: none;
                border-radius: 6px;
                cursor: pointer;
                transition: all 0.2s ease;
                font-weight: 500;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                background: transparent;
                border: 1px solid #d1d5db;
                color: #374151;
                box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            }
            
            .btn-primary {
                border-color: #3b82f6;
                color: #3b82f6;
            }
            
            .btn-secondary {
                border-color: #6b7280;
                color: #6b7280;
            }
            
            .btn-success {
                border-color: #10b981;
                color: #10b981;
            }
            
            .btn-danger {
                border-color: #ef4444;
                color: #ef4444;
            }
            
            .btn-primary:hover, .btn-secondary:hover, .btn-success:hover, .btn-danger:hover {
                background-color: #f9fafb;
                transform: translateY(-1px);
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            }
            
            .btn-primary:active, .btn-secondary:active, .btn-success:active, .btn-danger:active {
                transform: translateY(0);
                box-shadow: none;
            }
            
            .btn-primary i, .btn-secondary i, .btn-success i, .btn-danger i {
                font-size: 0.9rem;
            }

            /* Autres styles */
            #autoGenModal {
                display: none;
                position: fixed;
                z-index: 1050;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                overflow: auto;
                background-color: rgba(0,0,0,0.5);
            }

            .modal-container {
                background-color: #fefefe;
                margin: 5% auto;
                padding: 30px;
                border-radius: 12px;
                box-shadow: 0 5px 30px rgba(0, 0, 0, 0.1);
                width: 90%;
                max-width: 1200px;
                position: relative;
                max-height: 85vh;
                overflow-y: auto;
                border: none;
            }

            .modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-bottom: 1px solid #eaeaea;
                padding-bottom: 15px;
                margin-bottom: 20px;
            }

            .modal-title {
                font-size: 1.8rem;
                color: #2c3e50;
                font-weight: 600;
            }

            .close-modal {
                background: none;
                border: none;
                font-size: 1.8rem;
                cursor: pointer;
                color: #7f8c8d;
                transition: color 0.2s;
                width: 40px;
                height: 40px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .close-modal:hover {
                background-color: #f5f5f5;
                color: #e74c3c;
            }

            /* Configuration de masse */
            .bulk-config-section {
                background-color: #f8faff;
                border-radius: 10px;
                padding: 20px;
                margin-bottom: 25px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            }

            .section-title {
                font-size: 1.4rem;
                color: #3498db;
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .section-title i {
                font-size: 1.2rem;
            }

            .bulk-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
                gap: 25px;
            }

            .bulk-control-group {
                background: white;
                padding: 15px;
                border-radius: 8px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            }

            .bulk-control-group h4 {
                margin-top: 0;
                margin-bottom: 15px;
                color: #2c3e50;
                font-size: 1.1rem;
            }

            .control-row {
                display: flex;
                gap: 10px;
                margin-bottom: 10px;
                align-items: center;
            }

            .control-row label {
                min-width: 120px;
                font-weight: 500;
            }

            .bulk-input {
                flex: 1;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 6px;
                font-size: 1rem;
                transition: border-color 0.3s;
            }

            .bulk-input:focus {
                border-color: #3498db;
                outline: none;
                box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
            }

            /* Tableau de sélection en masse */
            .bulk-availability-container {
                margin-top: 10px;
            }

            .availability-grid-style {
                border-collapse: collapse;
                width: 100%;
                user-select: none;
            }

            .availability-grid-style th {
                background-color: #f2f6fc;
                padding: 6px;
                font-size: 0.85rem;
                font-weight: 600;
                color: #2c3e50;
                border: 1px solid #e0e0e0;
            }

            .availability-grid-style td {
                border: 1px solid #e0e0e0;
                text-align: center;
                padding: 0;
                background-color: white;
                transition: background-color 0.2s;
            }

            #bulkAvailabilityGrid th {
                padding: 10px 8px;
            }
            
            #bulkEditPanel {
                margin-bottom: 15px;
            }

            .constraints-table thead {
                position: sticky;
                top: 0;
                z-index: 10;
                background-color: #f8faff;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            }

            #bulkAvailabilityGrid td {
                width: 50px;
                height: 20px;
                cursor: pointer;
                position: relative;
            }

            #bulkAvailabilityGrid td:after {
                content: "";
                position: absolute;
                top: 50%;
                left: 50%;
                width: 20px;
                height: 20px;
                border-radius: 4px;
                background: #e3f2fd;
                transform: translate(-50%, -50%);
                transition: all 0.2s;
            }

            #bulkAvailabilityGrid td.selected:after {
                background: #3498db;
                width: 24px;
                height: 24px;
            }

            #bulkAvailabilityGrid td:hover:after {
                background: #bbdefb;
            }

            .formateurs-list {
                background-color: white;
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            }

            .constraints-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 0.95rem;
            }

            .constraints-table td {
                padding: 12px 15px;
                border-bottom: 1px solid #f0f0f0;
                vertical-align: middle;
            }

            .constraints-table tr:last-child td {
                border-bottom: none;
            }

            .constraints-table tr:hover td {
                background-color: #f9fbfe;
            }

            .formateur-name-col {
                font-weight: 600;
                min-width: 150px;
            }

            .tags-container {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                padding: 5px;
                min-height: 40px;
            }

            .space-tag {
                background: #e0f2fe;
                color: #0369a1;
                padding: 5px 12px;
                border-radius: 20px;
                font-size: 0.85rem;
                display: inline-flex;
                align-items: center;
                gap: 5px;
            }

            .remove-tag {
                background: none;
                border: none;
                color: #0369a1;
                cursor: pointer;
                font-weight: bold;
                font-size: 1rem;
                padding: 0;
                line-height: 1;
                margin-left: 5px;
                opacity: 0.7;
                transition: opacity 0.2s;
            }

            .remove-tag:hover {
                opacity: 1;
            }

            .mini-grid-container {
                padding: 5px 0;
            }

            .availability-mini-grid {
                border-collapse: collapse;
                width: 100%;
            }

            .availability-mini-grid th {
                padding: 3px 5px;
                font-weight: normal;
                font-size: 0.7rem;
                color: #7f8c8d;
            }

            .availability-mini-grid td {
                width: 20px;
                height: 10px;
                border-radius: 3px;
                cursor: pointer;
                background-color: #d5f5e3;
                position: relative;
                transition: all 0.2s;
            }

            .availability-mini-grid td:after {
                content: "";
                position: absolute;
                top: 50%;
                left: 50%;
                width: 12px;
                height: 12px;
                border-radius: 2px;
                background: #27ae60;
                transform: translate(-50%, -50%);
                transition: all 0.2s;
            }

            .availability-mini-grid td.unavailable:after {
                background: #e74c3c;
            }

            .availability-mini-grid td:hover:after {
                transform: translate(-50%, -50%) scale(1.2);
            }

            .modal-footer {
                display: flex;
                justify-content: flex-end;
                margin-top: 25px;
                padding-top: 20px;
                border-top: 1px solid #eee;
            }

            #runAutoGenerateBtn {
                padding: 12px 25px;
                font-size: 1.1rem;
                font-weight: 600;
                border: none;
                border-radius: 8px;
                cursor: pointer;
                background: #10b981;
                color: white;
                display: flex;
                align-items: center;
                gap: 10px;
                transition: all 0.3s ease;
            }

            #runAutoGenerateBtn:hover {
                background: #059669;
                transform: translateY(-2px);
            }

            .selected-row {
                background-color: #f0f7ff !important;
                position: relative;
            }

            .selected-row:after {
                content: "";
                position: absolute;
                top: 0;
                left: 0;
                width: 4px;
                height: 100%;
                background: #3498db;
            }

            @media (max-width: 900px) {
                .modal-container {
                    width: 95%;
                    padding: 20px;
                }
                
                .bulk-grid {
                    grid-template-columns: 1fr;
                }
                
                .constraints-table {
                    display: block;
                    overflow-x: auto;
                }
            }

            @media (max-width: 600px) {
                .control-row {
                    flex-direction: column;
                    align-items: flex-start;
                }
                
                .control-row label {
                    margin-bottom: 5px;
                }
                
                #tauxContainer {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 8px;
                }
            }

            .bulk-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
                gap: 25px;
            }
            .taux-total-warning {
                color: #e74c3c !important;
            }

            /* Styles améliorés pour les inputs */
            .taux-input, #bulkHours, #bulkTeams, #bulkSpaceSelect {
                transition: all 0.3s ease;
                outline: none;
            }
            
            .taux-input:focus, #bulkHours:focus, #bulkTeams:focus, #bulkSpaceSelect:focus {
                border-color: #3498db !important;
                box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1) !important;
            }
            
            /* Style pour les labels */
            label {
                font-weight: 500;
                color: #2c3e50;
                transition: color 0.3s;
            }
            
            /* Style pour les en-têtes */
            h4 {
                color: #2c3e50;
                font-weight: 600;
                margin-bottom: 10px;
            }
        </style>
    `;

    let tableHTML = `
        <table class="constraints-table">
            <thead>
                <tr>
                    <th style="width: 20px;"><input type="checkbox" id="selectAllFormateurs"></th>
                    <th>Formateur</th>
                    <th>Heures/Sem.</th>
                    <th>Sessions TEAMS</th>
                    <th>Espaces Autorisés</th>
                    <th style="width: 220px;">Indisponibilités</th>
                </tr>
            </thead>
            <tbody>`;

    appData.formateurs.forEach(formateur => {
        tableHTML += `<tr data-formateur="${formateur}">
            <td><input type="checkbox" class="formateur-select-checkbox"></td>
            <td class="formateur-name-col">${formateur}</td>
            <td><input type="number" class="formateur-hours-autogen" value="25" step="2.5"></td>
            <td><input type="number" class="formateur-teams-sessions" value="0"></td>
            <td><div class="tags-container assigned-spaces-container"></div></td>
            
            <td>
                <table class="availability-grid-style availability-mini-grid">
                    
                    <tbody>
                        ${jours.map(j => `
                            <tr>
                                <th>${j.substring(0,3)}</th>
                                ${seances.map(s => `<td class="mini-slot" data-jour="${j}" data-seance="${s}" title="${j} ${s}"></td>`).join('')}
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </td>
        </tr>`;
    });

    tableHTML += `</tbody></table>`;
    
    const modalContentWrapper = container.parentElement;

    const existingPanel = modalContentWrapper.querySelector('#bulkEditPanel');
    if (existingPanel) {
        existingPanel.remove();
    }
    
    container.insertAdjacentHTML('beforebegin', bulkEditPanelHTML);
    
    container.innerHTML = tableHTML;

    document.getElementById('autoGenModal').style.display = 'block';

    try {
        const savedConfigJSON = localStorage.getItem('autoGenConfig_v1');
        if (savedConfigJSON) {
            const savedConfig = JSON.parse(savedConfigJSON);
            document.querySelectorAll('#autoGenConstraintsContainer tr[data-formateur]').forEach(row => {
                const formateur = row.dataset.formateur;
                if (savedConfig[formateur]) {
                    const config = savedConfig[formateur];
                    
                    row.querySelector('.formateur-hours-autogen').value = config.hours || 25;
                    row.querySelector('.formateur-teams-sessions').value = config.teamsSessions || 0;
                    
                    const spacesContainer = row.querySelector('.assigned-spaces-container');
                    spacesContainer.innerHTML = '';
                    if(config.spaces && Array.isArray(config.spaces)) {
                       config.spaces.forEach(space => addSpaceTag(row, space));
                    }
                    
                    if(config.unavailable && Array.isArray(config.unavailable)) {
                        config.unavailable.forEach(slot => {
                            const miniSlot = row.querySelector(`.mini-slot[data-jour="${slot.jour}"][data-seance="${slot.seance}"]`);
                            if (miniSlot) miniSlot.classList.add('unavailable');
                        });
                    }
                }
            });
            showNotification("Dernière configuration chargée.", "info");
        }
    } catch(e) {
        console.error("Erreur lors du chargement de la configuration sauvegardée:", e);
        localStorage.removeItem('autoGenConfig_v1');
    }

    setupBulkEventListeners();
}

    function updateSelectionInfo() {
    const infoPanel = document.getElementById('bulkSelectionInfo');
    const selectedRows = document.querySelectorAll('#autoGenConstraintsContainer tr.selected-row');
    const count = selectedRows.length;

    if (count === 0) {
        infoPanel.textContent = 'Aucun formateur sélectionné.';
    } else {
        const names = Array.from(selectedRows).map(row => row.dataset.formateur);
        let text = `<strong>${count} formateur(s) sélectionné(s) :</strong> `;
        if (names.length > 5) {
            text += names.slice(0, 5).join('- ') + ` et ${names.length - 5} autre(s)...`;
        } else {
            text += names.join('- ');
        }
        infoPanel.innerHTML = text;
    }
}

/**
 * Détermine l'année de formation (1A, 2A, 3A) à partir du nom d'un groupe.
 * Se base sur les 3 derniers caractères du nom.
 * @param {string} groupe - Le nom du groupe (ex: "TDI101", "DEV201").
 * @returns {string} - L'année de formation ('1A', '2A', '3A') ou 'N/A' si non identifiable.
 */
 function getAnneeFormation(groupe) {
  if (!groupe || typeof groupe !== 'string' || groupe.length < 3) {
    return 'N/A';
  }

  const codeAnnee = parseInt(groupe.slice(-3), 10);

  if (isNaN(codeAnnee)) {
    return 'N/A';
  }

  if (codeAnnee >= 100 && codeAnnee < 200) {
    return '1A';
  } else if (codeAnnee >= 200 && codeAnnee < 300) {
    return '2A';
  } else if (codeAnnee >= 300 && codeAnnee < 400) {
    return '3A';
  } else {
    return 'Autre';
  }
}

function updateTauxTotal() {
    const tauxInputs = document.querySelectorAll('.taux-input');
    let total = 0;
    tauxInputs.forEach(input => {
        total += parseFloat(input.value) || 0;
    });

    const totalValueElement = document.getElementById('tauxTotalValue');
    const totalContainerElement = document.getElementById('tauxTotal');
    
    totalValueElement.textContent = total.toFixed(0);

    if (total.toFixed(0) !== '100') {
        totalContainerElement.classList.add('taux-total-warning');
    } else {
        totalContainerElement.classList.remove('taux-total-warning');
    }
}

    /**
     * NOUVELLE FONCTION : Sauvegarde la configuration de la génération automatique
     * dans le localStorage.
     */
    function saveAutoGenConfig() {
        const config = {};
        document.querySelectorAll('#autoGenConstraintsContainer tr[data-formateur]').forEach(row => {
            const formateur = row.dataset.formateur;
            config[formateur] = {
                hours: parseFloat(row.querySelector('.formateur-hours-autogen').value) || 25,
                teamsSessions: parseInt(row.querySelector('.formateur-teams-sessions').value, 10) || 0,
                spaces: Array.from(row.querySelectorAll('.space-tag')).map(tag => tag.dataset.space),
                unavailable: Array.from(row.querySelectorAll('.mini-slot.unavailable')).map(slot => ({
                    jour: slot.dataset.jour,
                    seance: slot.dataset.seance
                }))
            };
        });
        localStorage.setItem('autoGenConfig_v1', JSON.stringify(config));
        console.log("Configuration de la génération auto sauvegardée.");
    }
    





    function setupBulkEventListeners() {
        const modal = document.getElementById('autoGenModal');
        if (!modal) return;
    
        const getSelectedRows = () => [...modal.querySelectorAll('.formateur-select-checkbox:checked')].map(cb => cb.closest('tr'));
    
        const updateSelectionInfo = () => {
            const selectedRows = getSelectedRows();
            const selectedCount = selectedRows.length;
            const totalCount = modal.querySelectorAll('.formateur-select-checkbox').length;
            const infoDiv = document.getElementById('bulkSelectionInfo');
            const selectAllCheckbox = document.getElementById('selectAllFormateurs');
            const counterText = `(${selectedCount} / ${totalCount})`;
    
            if (selectedCount === 0) {
                infoDiv.textContent = `Aucun formateur sélectionné ${counterText}`;
            } else {
                const selectedNames = selectedRows.map(row => row.querySelector('.formateur-name-col').textContent.trim());
                infoDiv.textContent = `Sélection : ${selectedNames.join(', ')} ${counterText}`;
            }
            
            selectAllCheckbox.checked = selectedCount > 0 && selectedCount === totalCount;
            selectAllCheckbox.indeterminate = selectedCount > 0 && selectedCount < totalCount;
    
            modal.querySelectorAll('tr[data-formateur]').forEach(row => {
                const checkbox = row.querySelector('.formateur-select-checkbox');
                row.classList.toggle('selected-row', checkbox && checkbox.checked);
            });
        };
    
        // ---- DÉLÉGATION D'ÉVÉNEMENTS ----
        modal.addEventListener('click', (event) => {
            const target = event.target;
            const actionTarget = target.closest('[data-action]');
            
            // Clic sur un mini slot d'indisponibilité
            if (target.matches('.mini-slot')) {
                target.classList.toggle('unavailable');
                saveAutoGenConfig(); // Sauvegarder après modification
                return;
            }
    
            if (!actionTarget) return;
            const action = actionTarget.dataset.action;
            const selectedRows = getSelectedRows();
    
            if (selectedRows.length === 0 && !['block-availability', 'unblock-availability'].includes(action)) {
                 if(action === "add-space" || action === "remove-space"){
                     // Pas de message d'erreur pour les actions sur les espaces pour ne pas être intrusif
                 } else {
                    showNotification("Veuillez sélectionner au moins un formateur.", "warning");
                    return;
                 }
            }
    
            switch (action) {
                case 'apply-hours':
                    const hours = document.getElementById('bulkHours').value;
                    selectedRows.forEach(row => row.querySelector('.formateur-hours-autogen').value = hours);
                    break;
                case 'apply-teams':
                    const teams = document.getElementById('bulkTeams').value;
                    selectedRows.forEach(row => row.querySelector('.formateur-teams-sessions').value = teams);
                    break;
                case 'add-space':
                    const spaceToAdd = document.getElementById('bulkSpaceSelect').value;
                    selectedRows.forEach(row => addSpaceTag(row, spaceToAdd));
                    break;
                case 'remove-space':
                    const spaceToRemove = document.getElementById('bulkSpaceSelect').value;
                    selectedRows.forEach(row => {
                        const tag = row.querySelector(`.space-tag[data-space="${spaceToRemove}"]`);
                        if (tag) tag.remove();
                    });
                    break;
                case 'remove-specific-space':
                    target.closest('.space-tag').remove();
                    break;
                case 'block-availability':
                case 'unblock-availability':
                    const shouldBlock = action === 'block-availability';
                    const slotsToChange = modal.querySelectorAll('#bulkAvailabilityGrid td.selected');
                    if (slotsToChange.length === 0) {
                         showNotification("Veuillez sélectionner des créneaux dans la grille principale.", "warning");
                         return;
                    }
                    if (selectedRows.length === 0) {
                         showNotification("Veuillez sélectionner au moins un formateur.", "warning");
                         return;
                    }
                    selectedRows.forEach(row => {
                        slotsToChange.forEach(slot => {
                            const { jour, seance } = slot.dataset;
                            const miniSlot = row.querySelector(`.mini-slot[data-jour="${jour}"][data-seance="${seance}"]`);
                            if (miniSlot) miniSlot.classList.toggle('unavailable', shouldBlock);
                        });
                    });
                    slotsToChange.forEach(slot => slot.classList.remove('selected'));
                    break;
            }
            saveAutoGenConfig(); // Sauvegarder après chaque action de masse
        });
    
        modal.addEventListener('change', (event) => {
            if (event.target.matches('.formateur-select-checkbox, #selectAllFormateurs')) {
                const isChecked = document.getElementById('selectAllFormateurs').checked;
                if(event.target.id === 'selectAllFormateurs'){
                    modal.querySelectorAll('.formateur-select-checkbox').forEach(cb => cb.checked = isChecked);
                }
                updateSelectionInfo();
            } else if (event.target.matches('.formateur-hours-autogen, .formateur-teams-sessions')) {
                saveAutoGenConfig();
            }
        });
        
        document.getElementById('invertSelectionBtn')?.addEventListener('click', () => {
            modal.querySelectorAll('.formateur-select-checkbox').forEach(cb => cb.checked = !cb.checked);
            updateSelectionInfo();
        });
    
        const tauxContainer = document.getElementById('tauxContainer');
        if (tauxContainer) {
            tauxContainer.addEventListener('input', (e) => {
                if (e.target.classList.contains('taux-input')) updateTauxTotal();
            });
        }
    
        const grid = document.getElementById('bulkAvailabilityGrid');
        if (grid) {
            let isMouseDown = false;
            grid.addEventListener('mousedown', (e) => {
                if (e.target.tagName === 'TD') {
                    isMouseDown = true;
                    e.target.classList.toggle('selected');
                    e.preventDefault();
                }
            });
            grid.addEventListener('mouseover', (e) => {
                if (isMouseDown && e.target.tagName === 'TD') e.target.classList.add('selected');
            });
            document.addEventListener('mouseup', () => isMouseDown = false);
        }
        
        updateSelectionInfo();
        if (tauxContainer) updateTauxTotal();
    }

function addSpaceTag(row, space) {
    const container = row.querySelector('.assigned-spaces-container');
    if (!container) return;
    if (container.querySelector(`.space-tag[data-space="${space}"]`)) return;

    const tag = document.createElement('span');
    tag.className = 'space-tag';
    tag.dataset.space = space;
    tag.innerHTML = `${space}<button class="remove-tag" data-action="remove-specific-space" title="Retirer">×</button>`;
    container.appendChild(tag);
}

/**
 * Trouve le meilleur emplacement possible (jour, séance) pour une séance de 2.5h.
 * Privilégie fortement les créneaux adjacents à des cours existants.
 * @returns {object|null} - Le meilleur slot trouvé ou null.
 */
 function findBestSlotForSingle(task, newTimetableData, trackers, constraints) {
    let bestSlot = null;
    let highestScore = -1;
    const formateur = task.formateur;
    const formateurSchedule = newTimetableData[formateur];

    for (const jour of jours) {
        for (const seance of seances) {
            const currentSlot = { jour, seance };
            
            // On utilise la fonction de validation dédiée
            if (!isSlotValidForSingle(task, currentSlot, trackers, constraints)) {
                continue;
            }

            // Si le créneau est valide, on calcule son score
            let score = 1;
            const seanceIndex = seances.indexOf(seance);
            
            // Très gros bonus si le créneau est à côté d'un autre cours du même formateur
            if (seanceIndex > 0 && formateurSchedule[jour][seances[seanceIndex - 1]].groupe) {
                score += 50; 
            }
            if (seanceIndex < seances.length - 1 && formateurSchedule[jour][seances[seanceIndex + 1]].groupe) {
                score += 50;
            }
            
            // Petit bonus si le formateur travaille déjà ce jour-là
            if (seances.some(s => formateurSchedule[jour][s].groupe)) {
               score += 10;
            }
            
            if (score > highestScore) {
                highestScore = score;
                bestSlot = currentSlot;
            }
        }
    }
    return bestSlot;
}

/**
 * =========================================================================
 * ==        LOGIQUE DE GÉNÉRATION AUTOMATIQUE (V5) - ROBUSTE ET CORRIGÉE ==
 * =========================================================================
 * Stratégie :
 * 1. Forcer le placement de blocs de 5h avec une vérification stricte des contraintes.
 * 2. Placer les séances restantes de 2.5h.
 * 3. Assurer des résultats variés grâce à la randomisation contrôlée.
 */

// --- FONCTION PRINCIPALE ---

async function runAutoGeneration() {
    document.getElementById('weekLoader').style.display = 'flex';
    await new Promise(resolve => setTimeout(resolve, 50));

    // 1. Extraire les contraintes de l'interface
    saveAutoGenConfig();
    autoGenConstraints = {};
    document.querySelectorAll('#autoGenConstraintsContainer tr[data-formateur]').forEach(row => {
        const formateur = row.dataset.formateur;
        autoGenConstraints[formateur] = {
            hours: parseFloat(row.querySelector('.formateur-hours-autogen').value) || 0,
            teamsSessions: parseInt(row.querySelector('.formateur-teams-sessions').value, 10) || 0,
            spaces: Array.from(row.querySelectorAll('.space-tag')).map(tag => tag.dataset.space),
            unavailable: Array.from(row.querySelectorAll('.mini-slot.unavailable')).map(slot => ({
                jour: slot.dataset.jour,
                seance: slot.dataset.seance
            }))
        };
    });

    // 2. Initialiser l'emploi du temps vide et les trackers
    let newTimetableData = {};
    let trackers = { formateurHours: {}, formateurTeamsCount: {}, roomSlots: {}, groupSlots: {}, formateurSlots: {}, groupHours: {} };
    appData.formateurs.forEach(f => {
        newTimetableData[f] = {};
        trackers.formateurHours[f] = 0;
        trackers.formateurTeamsCount[f] = 0;
        jours.forEach(j => {
            newTimetableData[f][j] = {};
            seances.forEach(s => newTimetableData[f][j][s] = { groupe: '', module: '', salle: '' });
        });
    });

    // 3. Créer et prioriser les "besoins de cours"
    const semestresChoisis = new Set();
    if (document.getElementById('forceS1').checked) semestresChoisis.add('S1');
    if (document.getElementById('forceS2').checked) semestresChoisis.add('S2');
    if (document.getElementById('forceAnnual').checked) semestresChoisis.add('Annual');

    const affectationsFiltrees = appData.affectations.filter(aff =>
        semestresChoisis.has(getModuleSemester(aff.formateur, aff.groupe, aff.module))
    );

    const courseRequirements = createCourseRequirements(affectationsFiltrees);
    const prioritizedCourses = prioritizeTasks(courseRequirements, autoGenConstraints);

    let unplacedTasksLog = [];

    // --- PHASE UNIQUE : Placement forcé de TOUTES les sessions en blocs de 5h ---
console.log("Phase unique : Placement forcé de toutes les sessions en blocs de 5h...");
prioritizedCourses.forEach(course => {
    // Forcer TOUTES les sessions à être des blocs de 5h (arrondi supérieur)
    const numBlocksToPlace = Math.ceil(course.sessionsNeeded / 2);
    
    for (let i = 0; i < numBlocksToPlace; i++) {
        const bestSlots = findBestSlotsForBlock(course, newTimetableData, trackers, autoGenConstraints);
        if (bestSlots.length > 0) {
            const chosenSlot = bestSlots[Math.floor(Math.random() * bestSlots.length)];
            tryPlaceBlock(course, chosenSlot, newTimetableData, trackers);
            // Décrémenter de 2 car on place toujours un bloc complet
            course.sessionsNeeded -= 2;
        } else {
            unplacedTasksLog.push({ 
                task: course, 
                reason: `Bloc 5h forcé (${course.groupe}) : N'a pas pu être placé (tour ${i + 1}/${numBlocksToPlace}).` 
            });
            // Ne pas passer à la phase 2 - on arrête ici pour cette tâche
            break;
        }
    }
    
    // Vérifier s'il reste des sessions non placées (cas d'erreur)
    if (course.sessionsNeeded > 0) {
        unplacedTasksLog.push({ 
            task: course, 
            reason: `Sessions restantes non placées (${course.groupe}) : Impossible de respecter la règle des blocs de 5h.` 
        });
    }
    // === PHASE 2 : Essayer de placer les sessions restantes une par une (si blocs impossibles)
    while (course.sessionsNeeded > 0) {
        const bestSlots = findBestSlotsForSingle(course, newTimetableData, trackers, autoGenConstraints);
        if (bestSlots.length > 0) {
            const chosenSlot = bestSlots[Math.floor(Math.random() * bestSlots.length)];
            tryPlaceSingle(course, chosenSlot, newTimetableData, trackers);
            course.sessionsNeeded -= 1;
        } else {
            unplacedTasksLog.push({ 
                task: course, 
                reason: `Session individuelle non placée (${course.groupe}) : aucune option disponible.` 
            });
            break;
        }
    }


});

    // 5. Finalisation
    renderTimetableFromData(newTimetableData);
    document.getElementById('autoGenModal').style.display = 'none';
    document.getElementById('weekLoader').style.display = 'none';

    calculateAndShowGroupHours(newTimetableData);

    console.log("Tâches non placées :", unplacedTasksLog);
    const unplacedCount = unplacedTasksLog.length;
    if (unplacedCount > 0) {
        showNotification(`${unplacedCount} tâche(s) n'a/n'ont pas pu être placée(s). Consultez la console.`, 'warning');
    } else {
        showNotification("Génération aléatoire terminée avec succès !", 'success');
    }
}

// --- FONCTIONS D'AIDE POUR LA V5 ---

function createCourseRequirements(affectations) {
    const courseMap = new Map();
    affectations.forEach(affectation => {
        const key = `${affectation.formateur}|${affectation.groupe}|${affectation.module}|${affectation.type}`;
        if (!courseMap.has(key)) {
            courseMap.set(key, { ...affectation, sessionsNeeded: 0 });
        }
        courseMap.get(key).sessionsNeeded++;
    });
    return Array.from(courseMap.values());
}

function getDifficultyScore(task, constraints) {
    const formateurConstraints = constraints[task.formateur];
    if (!formateurConstraints) return 0;
    const availableSlots = (jours.length * seances.length) - (formateurConstraints.unavailable?.length || 0);
    return (formateurConstraints.hours / (availableSlots || 1));
}

function prioritizeTasks(tasks, constraints) {
    // On mélange d'abord pour varier l'ordre des tâches de même difficulté
    shuffleArray(tasks);
    return tasks.sort((a, b) => getDifficultyScore(b, constraints) - getDifficultyScore(a, constraints));
}

function findBestSlotsForBlock(task, newTimetableData, trackers, constraints) {
    let bestSlots = [];
    let highestScore = -Infinity; // Utiliser -Infinity pour une comparaison plus sûre
    const formateur = task.formateur;
    const formateurConstraints = constraints[formateur];
    if (!formateurConstraints) return [];
    
    const pairs = [['S1', 'S2'], ['S3', 'S4']];
    const shuffledJours = [...jours];
    shuffleArray(shuffledJours);

    // NOUVELLE PARTIE : Bonus pour l'équilibrage des groupes
    const currentHours = trackers.groupHours[task.groupe] || 0;
    // Plus le nombre d'heures est bas, plus le bonus est élevé.
    // Le poids (ex: * 10) rend ce critère plus important.
    const balanceBonus = (40 - currentHours) * 10; 

    for (const jour of shuffledJours) {
        for (const pair of pairs) {
            const [s1, s2] = pair;

            // ... (toutes les vérifications de conflits restent les mêmes) ...
            if (formateurConstraints.unavailable.some(u => u.jour === jour && (u.seance === s1 || u.seance === s2))) continue;
            if (trackers.formateurSlots[`${jour}-${s1}-${formateur}`] || trackers.formateurSlots[`${jour}-${s2}-${formateur}`]) continue;
            if (isGroupBusy(jour, s1, task.groupe, trackers) || isGroupBusy(jour, s2, task.groupe, trackers)) continue;
            if (trackers.formateurHours[formateur] + (SEANCE_DURATION * 2) > formateurConstraints.hours) continue;

            const shuffledSpaces = [...formateurConstraints.spaces];
            shuffleArray(shuffledSpaces);

            for (const space of shuffledSpaces) {
                let isSpaceAvailable = false;
                if (task.type === 'synchrone') {
                    if (space === 'TEAMS' && trackers.formateurTeamsCount[formateur] < formateurConstraints.teamsSessions) {
                        isSpaceAvailable = true;
                    }
                } else {
                    if (space !== 'TEAMS' && !trackers.roomSlots[`${jour}-${s1}-${space}`] && !trackers.roomSlots[`${jour}-${s2}-${space}`]) {
                        isSpaceAvailable = true;
                    }
                }

                if (isSpaceAvailable) {
                    // MODIFICATION DU CALCUL DE SCORE
                    let score = 100 + balanceBonus; // On intègre le bonus d'équilibrage
                    if (Object.values(newTimetableData[formateur][jour]).some(s => s.groupe)) score += 10; // Bonus pour regrouper les jours
                    
                    const newSlot = { jour, pair, salle: space };
                    if (score > highestScore) {
                        highestScore = score;
                        bestSlots = [newSlot];
                    } else if (score === highestScore) {
                        bestSlots.push(newSlot);
                    }
                }
            }
        }
    }
    return bestSlots;
}

function findBestSlotsForSingle(task, newTimetableData, trackers, constraints) {
    let bestSlots = [];
    let highestScore = -Infinity; // Utiliser -Infinity pour une comparaison plus sûre
    const formateur = task.formateur;
    const formateurConstraints = constraints[formateur];
    if (!formateurConstraints) return [];

    const shuffledJours = [...jours];
    shuffleArray(shuffledJours);

    // NOUVELLE PARTIE : Bonus pour l'équilibrage des groupes
    const currentHours = trackers.groupHours[task.groupe] || 0;
    const balanceBonus = (40 - currentHours) * 10;

    for (const jour of shuffledJours) {
        for (const seance of seances) {
             // ... (toutes les vérifications de conflits restent les mêmes) ...
             if (trackers.formateurSlots[`${jour}-${seance}-${formateur}`]) continue;
             if (isGroupBusy(jour, seance, task.groupe, trackers)) continue;
             if (formateurConstraints.unavailable.some(u => u.jour === jour && u.seance === seance)) continue;
             if (trackers.formateurHours[formateur] + SEANCE_DURATION > formateurConstraints.hours) continue;

             const shuffledSpaces = [...formateurConstraints.spaces];
             shuffleArray(shuffledSpaces);

            for (const space of shuffledSpaces) {
                let isSpaceAvailable = false;
                if (task.type === 'synchrone') {
                    if (space === 'TEAMS' && trackers.formateurTeamsCount[formateur] < formateurConstraints.teamsSessions) {
                        isSpaceAvailable = true;
                    }
                } else {
                    if (space !== 'TEAMS' && !trackers.roomSlots[`${jour}-${seance}-${space}`]) {
                        isSpaceAvailable = true;
                    }
                }

                if(isSpaceAvailable) {
                    // MODIFICATION DU CALCUL DE SCORE
                    let score = 1 + balanceBonus; // On intègre le bonus d'équilibrage
                    const seanceIndex = seances.indexOf(seance);
                    if (seanceIndex > 0 && newTimetableData[formateur][jour][seances[seanceIndex - 1]].groupe) score += 50;
                    if (seanceIndex < seances.length - 1 && newTimetableData[formateur][jour][seances[seanceIndex + 1]].groupe) score += 50;
                    if (Object.values(newTimetableData[formateur][jour]).some(s => s.groupe)) score += 10;
                    
                    const newSlot = { jour, seance, salle: space };
                    if (score > highestScore) {
                        highestScore = score;
                        bestSlots = [newSlot];
                    } else if (score === highestScore) {
                        bestSlots.push(newSlot);
                    }
                }
            }
        }
    }
    return bestSlots;
}

function tryPlaceBlock(task, slot, newTimetableData, trackers) {
    const { jour, pair, salle } = slot;
    const [s1, s2] = pair;
    const formateur = task.formateur;
    const sessionData = { groupe: task.groupe, module: task.module, salle: salle };
    
    newTimetableData[formateur][jour][s1] = sessionData;
    newTimetableData[formateur][jour][s2] = sessionData;
    
    trackers.formateurHours[formateur] += (SEANCE_DURATION * 2);
    trackers.formateurSlots[`${jour}-${s1}-${formateur}`] = true;
    trackers.formateurSlots[`${jour}-${s2}-${formateur}`] = true;
    
    const subGroups = task.groupe.trim().split(/\s+/).filter(Boolean);
    subGroups.forEach(g => {
        trackers.groupSlots[`${jour}-${s1}-${g}`] = true;
        trackers.groupSlots[`${jour}-${s2}-${g}`] = true;
        // AJOUT : Mettre à jour les heures du groupe
        trackers.groupHours[g] = (trackers.groupHours[g] || 0) + (SEANCE_DURATION * 2);
    });
    
    if (salle === 'TEAMS') {
        trackers.formateurTeamsCount[formateur]++;
    } else {
        trackers.roomSlots[`${jour}-${s1}-${salle}`] = true;
        trackers.roomSlots[`${jour}-${s2}-${salle}`] = true;
    }
}

function tryPlaceSingle(task, slot, newTimetableData, trackers) {
    const { jour, seance, salle } = slot;
    const formateur = task.formateur;
        
    newTimetableData[formateur][jour][seance] = { groupe: task.groupe, module: task.module, salle: salle };
    trackers.formateurHours[formateur] += SEANCE_DURATION;
    trackers.formateurSlots[`${jour}-${seance}-${formateur}`] = true;
    
    const subGroups = task.groupe.trim().split(/\s+/).filter(Boolean);
    subGroups.forEach(g => {
        trackers.groupSlots[`${jour}-${seance}-${g}`] = true;
        // AJOUT : Mettre à jour les heures du groupe
        trackers.groupHours[g] = (trackers.groupHours[g] || 0) + SEANCE_DURATION;
    });
    
    if (salle === 'TEAMS') {
        trackers.formateurTeamsCount[formateur]++;
    } else {
        trackers.roomSlots[`${jour}-${seance}-${salle}`] = true;
    }
}

function isGroupBusy(jour, seance, groupe, trackers) {
    const subGroups = groupe.trim().split(/\s+/).filter(Boolean);
    return subGroups.some(subGroup => trackers.groupSlots[`${jour}-${seance}-${subGroup}`]);
}

function shuffleArray(array) {
    for (let i = array.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [array[i], array[j]] = [array[j], array[i]];
    }
}

// --- FIN DE LA SECTION DE GÉNÉRATION AUTOMATIQUE ---





    function setupVoiceAssistant() {
        if (!SpeechRecognition) {
            voiceAssistantBtn.style.display = 'none';
            console.warn("L'API Web Speech n'est pas supportée par ce navigateur.");
            return;
        }
        voiceAssistantBtn.addEventListener('click', toggleListening);
        recognition.onresult = (event) => {
            const transcript = event.results[event.results.length - 1][0].transcript.trim();
            updateStatus(`J'ai entendu : "${transcript}"`);
            processCommand(transcript);
        };
        recognition.onerror = (event) => {
            console.error("Erreur de reconnaissance vocale:", event.error);
            updateStatus("Désolé, je n'ai pas compris.", 3000, true);
            stopListening();
        };
        recognition.onend = () => {
            stopListening();
        };
    }
    
    function toggleListening() {
        if (voiceAssistantBtn.classList.contains('listening')) {
            recognition.stop();
        } else {
            startListening();
        }
    }

function startListening() {
    voiceAssistantBtn.classList.add('listening');
    voiceAssistantBtn.querySelector('span i').className = 'fas fa-wave-square';
    updateStatus("Je vous écoute...", 0);
    recognition.start();
}

function stopListening() {
    voiceAssistantBtn.classList.remove('listening');
    voiceAssistantBtn.querySelector('span i').className = 'fas fa-microphone';
    setTimeout(() => assistantStatus.classList.remove('visible'), 500);
}

    function updateStatus(message, duration = 2000, isError = false) {
        assistantStatus.textContent = message;
        assistantStatus.style.backgroundColor = isError ? '#e74c3c' : 'rgba(0, 0, 0, 0.75)';
        assistantStatus.classList.add('visible');
        if (duration > 0) {
            setTimeout(() => {
                assistantStatus.classList.remove('visible');
            }, duration);
        }
    }
    
    function speak(text) {
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = 'fr-FR';
        utterance.rate = 1.1;
        speechSynthesis.cancel();
        speechSynthesis.speak(utterance);
    }

    let conversationContext = null;
    async function processCommand(command) {
        const GEMINI_API_KEY = 'AIzaSyCtvJkZ0xp7eSEIVVThjiYQ9HsK84ca_bU';
        if (GEMINI_API_KEY.includes('VOTRE_CLE')) {
            const errorMsg = "Erreur : La clé API Gemini n'a pas été configurée dans le code.";
            speak(errorMsg);
            updateStatus(errorMsg, 4000, true);
            console.error(errorMsg);
            return;
        }
        const API_URL = `https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=${GEMINI_API_KEY}`;
        updateStatus('Réflexion en cours...', 0);
        if (conversationContext && conversationContext.action === 'confirm_generation') {
            if (command.toLowerCase().match(/oui|lance|vas-y|confirme|ok|d'accord/)) {
                speak("Parfait, je lance la génération immédiatement.");
                openAutoGenModal();
                setTimeout(() => document.getElementById('runAutoGenerateBtn').click(), 500);
                conversationContext = null;
                return;
            } else {
                speak("D'accord, la génération automatique est annulée.");
                conversationContext = null;
                return;
            }
        }
        const currentFormateurs = appData.formateurs.map(f => f.toUpperCase());
        const formateursListString = currentFormateurs.length > 0 
            ? `Voici la liste des formateurs valides que tu dois utiliser : ${currentFormateurs.join(', ')}.` 
            : "Aucun formateur n'est chargé pour le moment.";
        const system_prompt = `
            Tu es un assistant IA pour une application de gestion d'emplois du temps. ${formateursListString}
            Ton rôle est de convertir la requête de l'utilisateur en un objet JSON structuré et de fournir une réponse textuelle pour initier un dialogue si nécessaire.
            Intents possibles : 'afficher_edt', 'enregistrer_edt', 'generer_edt', 'definir_contrainte', 'naviguer_page', 'effacer_edt', 'inconnue'.
            Le format de ta réponse DOIT être UNIQUEMENT un objet JSON.
            Format JSON :
            {
              "intent": "nom_de_l_intention",
              "parameters": {
                "semaine": null,
                "page": null, // 'accueil', 'edition', 'avancement', 'espaces'
                "formateur": null, // DOIT être un nom de la liste fournie.
                "type_contrainte": null, // 'heures' ou 'indisponibilite'
                "valeur": null
              },
              "response": "phrase de réponse naturelle pour l'utilisateur."
            }
            Exemples :
            - Utilisateur: "Va sur la page d'avancement" -> JSON: { "intent": "naviguer_page", "parameters": {"page": "avancement"}, "response": "J'ouvre la page d'avancement." }
            - Utilisateur: "Lance la génération automatique" -> JSON: { "intent": "generer_edt", "parameters": {}, "response": "Je peux lancer la génération. Dois-je vous confirmer les contraintes actuelles avant ?" }
            - Utilisateur: "Pour MARTIN, mets 30 heures" -> JSON: { "intent": "definir_contrainte", "parameters": {"formateur": "MARTIN", "type_contrainte": "heures", "valeur": 30}, "response": "Contrainte mise à jour pour Martin : 30 heures." }
            Maintenant, analyse la requête suivante : "${command}"
        `;
        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ contents: [{ parts: [{ text: system_prompt }] }] }),
            });
            if (!response.ok) throw new Error('Réponse de l\'API Google non valide.');
            const data = await response.json();
            const rawTextResponse = data.candidates[0].content.parts[0].text;
            const jsonText = rawTextResponse.replace(/```json/g, '').replace(/```/g, '').trim();
            const result = JSON.parse(jsonText);
            speak(result.response);
            switch (result.intent) {
                case 'afficher_edt':
                    const weekNumber = result.parameters.semaine;
                    if (weekNumber) {
                        const schoolYear = getSchoolYear(new Date());
                        let firstMonday = getWeekInfo(new Date(schoolYear, 8, 1)).startOfWeek;
                        let targetDate = new Date(firstMonday);
                        targetDate.setDate(firstMonday.getDate() + (weekNumber) * 7);
                        updateWeekSelection(targetDate);
                    }
                    break;
                case 'enregistrer_edt':
                    saveTimetable();
                    break;
                case 'effacer_edt':
                    resetTimetable();
                    break;
                case 'generer_edt':
                    handleGenerationCommand();
                    break;
                case 'definir_contrainte':
                    handleConstraintCommand(result.parameters);
                    break;
                case 'naviguer_page':
                    const page = result.parameters.page;
                    const pageMap = {
                        'accueil': 'emploi.html',
                        'edition': 'edition.html',
                        'avancement': 'avancement.html',
                        'espaces': 'espaces.html'
                    };
                    if (page && pageMap[page]) {
                        window.location.href = pageMap[page];
                    }
                    break;
                default:
                    console.log("Intent non reconnu par l'IA.");
                    break;
            }
        } catch (error) {
            console.error("Erreur de communication avec l'API Gemini:", error);
            const errorMessage = "Désolé, une erreur est survenue lors de la communication avec mon intelligence.";
            speak(errorMessage);
            updateStatus(errorMessage, 4000, true);
        }
    }

    function handleGenerationCommand() {
        openAutoGenModal();
        setTimeout(() => {
            let constraintsSummary = "Je suis prêt à lancer la génération. ";
            const constraintRows = document.querySelectorAll('#autoGenConstraintsContainer tr[data-formateur]');
            if (constraintRows.length > 0) {
                const firstRow = constraintRows[0];
                const formateur = firstRow.dataset.formateur;
                const heures = firstRow.querySelector('.formateur-hours-autogen').value;
                constraintsSummary += `À titre d'exemple, pour ${formateur}, le maximum est de ${heures} heures. Dois-je lancer la génération avec les réglages actuels ?`;
            } else {
                constraintsSummary += "Dois-je continuer ?";
            }
            speak(constraintsSummary);
            conversationContext = {
                action: 'confirm_generation'
            };
        }, 500);
    }

    function handleConstraintCommand(params) {
        if (!params.formateur) {
            speak("Veuillez spécifier un nom de formateur valide.");
            return;
        }
        openAutoGenModal();
        setTimeout(() => {
            const formateurRow = document.querySelector(`#autoGenConstraintsContainer tr[data-formateur="${params.formateur.toUpperCase()}"]`);
            if (!formateurRow) {
                speak(`Désolé, je ne trouve pas le formateur nommé ${params.formateur}.`);
                return;
            }
            if (!params.type_contrainte) {
                formateurRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                formateurRow.style.backgroundColor = '#fff9c4';
                return;
            }
            switch (params.type_contrainte) {
                case 'heures':
                    const hoursInput = formateurRow.querySelector('.formateur-hours-autogen');
                    if (hoursInput) {
                        hoursInput.value = params.valeur;
                        hoursInput.style.backgroundColor = '#a7ffeb';
                        setTimeout(() => hoursInput.style.backgroundColor = '', 2000);
                    }
                    break;
                case 'indisponibilite':
                    const [jour, seance] = params.valeur.split(/\s+/);
                    const checkbox = formateurRow.querySelector(`input[data-jour*="${jour}"][data-seance="${seance}"]`);
                    if (checkbox) {
                        checkbox.checked = true;
                        checkbox.parentElement.style.backgroundColor = '#ffcdd2';
                        setTimeout(() => checkbox.parentElement.style.backgroundColor = '', 2000);
                    }
                    break;
            }
        }, 500);
    }

    function getOccupiedRooms(jour, seance, currentSelect) {
      const occupied = new Set();
      const allSalleSelects = document.querySelectorAll(`select[data-jour="${jour}"][data-seance="${seance}"][data-type="salle"]`);
      
      allSalleSelects.forEach(select => {
        if (select !== currentSelect && select.value && select.value !== 'TEAMS') {
          occupied.add(select.value);
        }
      });
      return occupied;
    }

    function updateSalleOptions(selectElement) {
      const jour = selectElement.dataset.jour;
      const seance = selectElement.dataset.seance;
      const currentValue = selectElement.value;

      const occupiedRooms = getOccupiedRooms(jour, seance, selectElement);
      selectElement.innerHTML = '';

      const emptyOption = document.createElement('option');
      emptyOption.value = '';
      selectElement.appendChild(emptyOption);

      appData.espaces.forEach(espace => {
        if (!occupiedRooms.has(espace) || espace === currentValue) {
          const option = document.createElement('option');
          option.value = espace;
          option.textContent = espace;
          selectElement.appendChild(option);
        }
      });
      selectElement.value = currentValue;
    }

function getOccupiedGroups(jour, seance, currentSelect) {
    const occupied = new Set();
    const allGroupSelects = document.querySelectorAll(`select[data-jour="${jour}"][data-seance="${seance}"][data-type="groupe"]`);

    allGroupSelects.forEach(select => {
        if (select !== currentSelect && select.value) {
            const subGroups = select.value.trim().split(/\s+/);
            subGroups.forEach(g => {
                if (g) occupied.add(g);
            });
        }
    });
    return occupied;
}

function styleGroupOptions(selectElement) {
    const jour = selectElement.dataset.jour;
    const seance = selectElement.dataset.seance;
    const occupiedGroups = getOccupiedGroups(jour, seance, selectElement);

    for (const option of selectElement.options) {
        if (!option.value) continue;

        const optionSubGroups = option.value.trim().split(/\s+/);
        const isOccupied = optionSubGroups.some(g => occupiedGroups.has(g));

        if (isOccupied) {
            option.style.color = 'red';
            option.style.fontWeight = 'bold';
            option.textContent = `${option.value} (Pris)`;
        } else {
            option.style.color = '';
            option.style.fontWeight = '';
            option.textContent = option.value;
        }
    }
}

function formatFrenchDate(date) {
        return date.toLocaleDateString('fr-FR', { 
            day: '2-digit', 
            month: 'short'
        });
    }
    
    async function getSavedWeeks() {
        try {
            const response = await fetch('../api/data/get_all_timetables.php');
            if (!response.ok) throw new Error(`Erreur HTTP: ${response.status}`);
            
            const savedTimetables = await response.json();
            
            const weeks = savedTimetables.map(tt => {
                const weekInfo = parseWeekValue(tt.semaine);
                if (!weekInfo) return null;
                return {
                    value: tt.semaine,
                    label: `S${weekInfo.weekNumber}`,
                    dates: `${formatFrenchDate(weekInfo.startOfWeek)} - ${formatFrenchDate(weekInfo.endOfWeek)}`
                };
            }).filter(Boolean);

            weeks.sort((a, b) => b.value.localeCompare(a.value));
            return weeks;

        } catch (error) {
            console.error("Erreur lors de la récupération des semaines:", error);
            return []; 
        }
    }
    
    function parseWeekValue(weekValue) {
        const [year, week] = weekValue.split('-W');
        if (!year || !week) return null;
        
        const schoolYear = parseInt(year);
        const weekNumber = parseInt(week);
        
        const firstDayOfSeptember = new Date(schoolYear, 8, 1);
        let dayOfWeek = firstDayOfSeptember.getDay();
        let firstMonday = new Date(firstDayOfSeptember);
        
        if (dayOfWeek !== 1) {
            const offset = (dayOfWeek === 0) ? 1 : 8 - dayOfWeek;
            firstMonday.setDate(firstDayOfSeptember.getDate() + offset);
        }
        
        const startOfWeek = new Date(firstMonday);
        startOfWeek.setDate(firstMonday.getDate() + (weekNumber - 1) * 7);
        startOfWeek.setHours(0, 0, 0, 0);
        
        const endOfWeek = new Date(startOfWeek);
        endOfWeek.setDate(startOfWeek.getDate() + 6);
        
        return {
            schoolYear: schoolYear,
            weekNumber: weekNumber,
            startOfWeek: startOfWeek,
            endOfWeek: endOfWeek
        };
    }
    
async function showWeekDropdown() {
    const menu = document.getElementById('weekImportMenu');
    menu.innerHTML = '<div style="padding:10px; text-align:center;">Chargement...</div>';
    menu.classList.add('show');
    
    const savedWeeks = await getSavedWeeks();
    
    if (savedWeeks.length === 0) {
        menu.innerHTML = '<div style="padding:10px; text-align:center; color:#777;">Aucune semaine sauvegardée.</div>';
        return;
    }
    
    menu.innerHTML = '';
    const filteredWeeks = savedWeeks.filter(w => w.value !== currentWeekValue);
    
    if (filteredWeeks.length === 0) {
        menu.innerHTML = '<div style="padding:10px; text-align:center; color:#777;">Aucune autre semaine à importer.</div>';
        return;
    }
    
    filteredWeeks.forEach(week => {
        const weekElement = document.createElement('div');
        weekElement.className = 'week-import-item';
        const year = week.value.split('-W')[0];
        weekElement.innerHTML = `
            <div>
                <span class="week-import-label">${week.label} (${year})</span>
                <span class="week-import-dates" style="display:block; font-size:0.8em; color:#666;">${week.dates}</span>
            </div>
        `;
        weekElement.dataset.week = week.value;
        weekElement.onclick = () => importWeekData(week.value);
        menu.appendChild(weekElement);
    });
}
    
    async function importWeekData(sourceWeekValue) {
        if (sourceWeekValue === currentWeekValue) {
            showNotification("Vous ne pouvez pas importer la semaine active sur elle-même.", "warning");
            return;
        }

        const weekInfo = parseWeekValue(sourceWeekValue);
        const sourceLabel = `S${weekInfo.weekNumber}`;
        
        if (!confirm(`Voulez-vous écraser l'emploi du temps actuel avec celui de la semaine ${sourceLabel} ?`)) return;

        const loader = document.getElementById('weekLoader');
        loader.style.display = 'flex';
        
        try {
            const response = await fetch(`../api/data/get_timetable.php?semaine=${sourceWeekValue}`);
            const result = await response.json();

            if (!result.success) throw new Error(result.message || 'Impossible de charger la semaine source.');
            
            const importedData = result.data;
            
            timetableData = importedData;
            await saveTimetable();
            
            renderTimetableFromData(timetableData);
            showNotification(`EDT de ${sourceLabel} importé et enregistré pour la semaine actuelle !`, "success");

        } catch (error) {
            console.error("Erreur lors de l'importation:", error);
            showNotification(`Erreur d'importation: ${error.message}`, "error");
        } finally {
            loader.style.display = 'none';
            document.getElementById('weekImportMenu').classList.remove('show');
        }
    }
    
async function checkSessionAndLoad() {
        try {
            const response = await fetch('../api/auth/verify_session.php');
            const result = await response.json();

            if (!result.success) {
                window.location.href = 'login.html';
                return;
            }

            populateUserInfo(result.userData);
            setupProfileMenu();
            setupEventListeners();
            setupCustomCalendar();
            
            loadInitialData();
            
        } catch (error) {
            console.error('Session check failed:', error);
            window.location.href = 'login.html';
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        checkSessionAndLoad();
        document.getElementById('notificationClose').addEventListener('click', () => {
            document.getElementById('notification').classList.add('hide');
        });
    });
    
    function loadInitialData() {
    fetch('../api/data/get_base_data.php')
        .then(response => response.json())
        .then(result => {
            console.log("Données de base reçues:", result);

            appData = { formateurs: [], groupes: [], fusionGroupes: [], espaces: [], affectations: [] };

            if (result.success && result.data) {
                appData = result.data;
                if (!appData.formateurs || appData.formateurs.length === 0) {
                    showNotification("Fichier traité, mais aucun formateur trouvé. Vérifiez le contenu.", "warning");
                }
            } else {
                showNotification(result.message || 'Aucune donnée de base. Veuillez charger un fichier Excel.', "warning");
            }

            updateWeekSelection(new Date()); 
            generateTimetable(); 
        })
        .catch(error => {
            console.error('Erreur critique chargement données:', error);
            showNotification("Erreur de communication avec le serveur.", "error");
            appData = { formateurs: [], groupes: [], fusionGroupes: [], espaces: [], affectations: [] };
            generateTimetable();
        });
}


    function getInitials(name) {
        if (!name) return '--';
        const words = name.trim().split(' ');
        if (words.length === 1) return words[0].substring(0, 2).toUpperCase();
        return (words[0][0] + words[words.length - 1][0]).toUpperCase();
    }

    function populateUserInfo(userData) {
    if (!userData) return;
    
    document.getElementById('profile-initials').textContent = getInitials(userData.nom);
    document.getElementById('dropdown-user-name').textContent = userData.nom;
    document.getElementById('dropdown-user-email').textContent = userData.email;
}

function setupProfileMenu() {
    const profileButton = document.getElementById('profile-button');
    const profileDropdown = document.getElementById('profile-dropdown');

    if (profileButton && profileDropdown) {
        profileButton.addEventListener('click', (event) => {
            event.stopPropagation(); 
            profileDropdown.classList.toggle('show');
        });
        
        window.addEventListener('click', (event) => {
            if (profileDropdown.classList.contains('show') && !profileDropdown.contains(event.target)) {
                profileDropdown.classList.remove('show');
            }
        });
    }
}
function isSlotAvailableForSession(jour, seance, sessionToCheck, excludeFormateur) {
    if (!sessionToCheck || !sessionToCheck.groupe) return true;

    const groupesToCheck = sessionToCheck.groupe.trim().split(/\s+/).filter(Boolean);
    const salleToCheck = sessionToCheck.salle;

    for (const formateur in timetableData) {
        if (formateur === excludeFormateur) continue;

        const otherSession = timetableData[formateur][jour][seance];
        if (!otherSession || !otherSession.groupe) continue;

        if (salleToCheck && salleToCheck !== 'TEAMS' && otherSession.salle === salleToCheck) {
            return false;
        }

        const autresGroupes = otherSession.groupe.trim().split(/\s+/).filter(Boolean);
        if (groupesToCheck.some(g => autresGroupes.includes(g))) {
            return false;
        }
    }
    return true;
}

     function getModuleSemester(formateur, groupe, module) {
        if (!formateur || !groupe || !module) {
            return null;
        }
        const subGroups = groupe.trim().split(/\s+/).filter(Boolean);
        for (const subGroup of subGroups) {
            const affectation = appData.affectations.find(a =>
                a.formateur === formateur &&
                a.groupe === subGroup &&
                a.module === module
            );

            if (affectation) {
                const s1Hours = parseFloat(affectation.s1_heures) || 0;
                const s2Hours = parseFloat(affectation.s2_heures) || 0;

                if (s1Hours > 0 && s2Hours > 0) return "Annual";
                if (s1Hours > 0) return "S1";
                if (s2Hours > 0) return "S2";
            }
        }
        return null;
    }

    /**
 * Calcule la masse horaire pour chaque groupe à partir des données de l'emploi du temps,
 * puis affiche les résultats dans une fenêtre modale.
 * @param {object} timetableData - Les données complètes de l'emploi du temps généré.
 */
/**
 * Calcule la masse horaire pour chaque groupe, puis affiche les résultats
 * sous forme de graphique et de tableau dans une fenêtre modale.
 * @param {object} timetableData - Les données complètes de l'emploi du temps généré.
 */
 /**
 * Calcule la masse horaire pour chaque groupe, puis affiche les résultats
 * sous forme de graphique (AVEC VALEURS) et de tableau dans une fenêtre modale.
 * @param {object} timetableData - Les données complètes de l'emploi du temps généré.
 */
function calculateAndShowGroupHours(timetableData) {
    // ... (la partie calcul des heures reste identique) ...
    const groupHours = {};
    const SEANCE_DURATION = 2.5;

    for (const formateur in timetableData) {
        for (const jour in timetableData[formateur]) {
            for (const seance in timetableData[formateur][jour]) {
                const session = timetableData[formateur][jour][seance];
                if (session.groupe) {
                    const subGroups = session.groupe.trim().split(/\s+/).filter(Boolean);
                    subGroups.forEach(g => {
                        groupHours[g] = (groupHours[g] || 0) + SEANCE_DURATION;
                    });
                }
            }
        }
    }

    const sortedGroups = Object.entries(groupHours)
        .map(([groupe, heures]) => ({ groupe, heures }))
        .sort((a, b) => b.heures - a.heures);

    // ... (la partie qui remplit le tableau reste identique) ...
    const tbody = document.getElementById('groupHoursResultTbody');
    tbody.innerHTML = '';
    if (sortedGroups.length === 0) {
        tbody.innerHTML = '<tr><td colspan="2" style="text-align:center;">Aucune heure n\'a été affectée.</td></tr>';
    } else {
        const alphabeticallySortedGroups = [...sortedGroups].sort((a,b) => a.groupe.localeCompare(b.groupe));
        alphabeticallySortedGroups.forEach(({ groupe, heures }) => {
            tbody.innerHTML += `<tr><td>${groupe}</td><td>${heures.toFixed(1)} h</td></tr>`;
        });
    }
    
    // --- PARTIE GRAPHIQUE MISE À JOUR ---
    const ctx = document.getElementById('groupHoursChartCanvas').getContext('2d');
    const labels = sortedGroups.map(g => g.groupe);
    const data = sortedGroups.map(g => g.heures);

    if (groupHoursChart) {
        groupHoursChart.destroy();
    }

    groupHoursChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Heures par Groupe',
                data: data,
                backgroundColor: 'rgba(54, 162, 235, 0.6)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Nombre d\'heures'
                    }
                }
            },
            // ========================================================
            // == NOUVELLE CONFIGURATION POUR AFFICHER LES VALEURS ==
            // ========================================================
            plugins: {
                legend: {
                    display: false
                },
                title: {
                    display: true,
                    text: '',
                    font: { size: 16 }
                },
                // Configuration spécifique pour chartjs-plugin-datalabels
                datalabels: {
                    anchor: 'end',    // Positionne l'étiquette à la fin (en haut) de la barre.
                    align: 'top',     // Aligne le texte au-dessus du point d'ancrage.
                    offset: 4,        // Ajoute un petit espace au-dessus de la barre.
                    color: '#444',    // Couleur du texte pour une bonne lisibilité.
                    font: {
                        weight: 'bold' // Met le texte en gras.
                    },
                    // Formate le nombre pour afficher "7.5 h" par exemple.
                    formatter: (value, context) => {
                        return value.toFixed(1) + ' h';
                    }
                }
            }
        }
    });

    const modalTitleElement = document.getElementById('groupHoursModalTitle');
    let weekLabel = '';

    // On vérifie que la variable de la semaine est bien définie
    if (currentWeekValue && currentWeekValue.includes('-W')) {
        // On extrait le numéro de la semaine de la chaîne "ANNEE-WNUMERO"
        const weekNumber = currentWeekValue.split('-W')[1];
        if (weekNumber) {
           weekLabel = ` - S${weekNumber}`;
        }
    }

    // On met à jour le contenu du titre
    modalTitleElement.textContent = `Masse Horaire par Groupe${weekLabel}`;

    // Afficher la modale
    document.getElementById('groupHoursModal').style.display = 'block';
}