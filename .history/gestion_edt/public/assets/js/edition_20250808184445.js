        // Le code JavaScript reste inchangé car les IDs des boutons
        // et leur fonctionnement logique n'ont pas été modifiés.
        // --- VARIABLES GLOBALES ---
        let historique = [];
        let selectedWeekIndex = -1; // Initialiser à -1 pour indiquer qu'aucune semaine n'est sélectionnée
        let currentView = 'global';
        let currentMode = 'formateur';
        let selectedTeacher = '', selectedGroup = '';
        let teachers = [], groups = new Set();
        const days = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
        const slots = ['S1', 'S2', 'S3', 'S4'];
        
        // Variables pour le calendrier
        let selectedDate = new Date();
        let calendarCurrentDate = new Date();
        let currentWeekValue = ''; 
        
        function getCacheBuster() { return 't=' + new Date().getTime(); }
    
        function loadData() {
        // On remplace simplement l'URL
        fetch(`../api/data/get_all_timetables.php`)
            .then(response => response.json())
            .then(data => {
                // Le reste de votre code est identique car la structure de données est la même !
                historique = data.sort((a, b) => (a.semaine || "").localeCompare(b.semaine || ""));
                initializeApp();
            }).catch(error => {
                console.error('Erreur de chargement de l\'historique:', error);
                historique = []; // On initialise avec un tableau vide en cas d'erreur
                initializeApp();
            });
    }
        function initializeApp() {
            if (historique.length === 0) {
                console.warn("L'historique des semaines est vide. Le calendrier pourrait ne pas fonctionner comme prévu.");
            }
            setupEventListeners();
            setupCustomCalendar();
            updateWeekSelection(new Date()); 
            updateButtonVisibility(); // MODIFICATION : Mettre à jour l'état initial des boutons
        }
        
        // --- GESTIONNAIRES D'ÉVÉNEMENTS ---
        function setupEventListeners() {
            document.getElementById('view-toggle-btn').addEventListener('click', toggleView);
            document.getElementById('print-global-btn').addEventListener('click', () => window.print());
            document.getElementById('tab1').addEventListener('change', () => switchMode('formateur'));
            document.getElementById('tab2').addEventListener('change', () => switchMode('groupe'));
            setupAutocomplete('teacher');
            setupAutocomplete('group');
            document.getElementById('download-teacher-pdf').addEventListener('click', () => downloadPDF('teacher'));
            document.getElementById('download-group-pdf').addEventListener('click', () => downloadPDF('group'));
            document.getElementById('download-global-pdf-btn').addEventListener('click', () => downloadGlobalPDF(currentMode));
        }
    
        // MODIFICATION : Nouvelle fonction pour gérer la visibilité des boutons
        function updateButtonVisibility() {
            const isGlobalView = currentView === 'global';
            const isFormateurMode = currentMode === 'formateur';
    
            // Afficher/masquer le groupe de boutons de la vue globale
            document.getElementById('global-view-buttons').style.display = isGlobalView ? 'flex' : 'none';
    
            // Afficher/masquer les boutons PDF spécifiques à la vue détaillée
            document.getElementById('download-teacher-pdf').classList.toggle('hidden', !(!isGlobalView && isFormateurMode));
            document.getElementById('download-group-pdf').classList.toggle('hidden', !(!isGlobalView && !isFormateurMode));
    
            // Mettre à jour le texte du bouton de basculement de vue
            const toggleBtn = document.getElementById('view-toggle-btn');
            if (isGlobalView) {
                toggleBtn.innerHTML = '<i class="fas fa-filter"></i> Vue Détaillée';
            } else {
                toggleBtn.innerHTML = '<i class="fas fa-globe-americas"></i> Vue Globale';
            }
        }
    
    
        function toggleView() {
            currentView = (currentView === 'global') ? 'specific' : 'global';
            document.getElementById('global-view-section').classList.toggle('hidden');
            document.getElementById('specific-search-section').classList.toggle('hidden');
            
            updateButtonVisibility(); 
    
            if (currentView === 'specific') {
                displayDefaultSpecificView();
            } else {
                updateAllViews();
            }
        }
    
        function switchMode(mode) {
            currentMode = mode;
            document.getElementById('formateur-section').classList.toggle('hidden', mode !== 'formateur');
            document.getElementById('groupe-section').classList.toggle('hidden', mode !== 'groupe');
            
            updateButtonVisibility(); 
    
            if (currentView === 'global') {
                displayGlobalSchedule();
            } else {
                displayDefaultSpecificView();
            }
        }
    
        // --- MISE À JOUR CENTRALE ---
        function updateAllViews() {
            updateDataForWeek();
            if (currentView === 'global') {
                displayGlobalSchedule();
            } else {
                updateAutocompleteLists();
                if (currentMode === 'formateur' && selectedTeacher) displayTeacherSchedule(selectedTeacher);
                if (currentMode === 'groupe' && selectedGroup) displayGroupSchedule(selectedGroup);
            }
        }
    
        function updateDataForWeek() {
            if (selectedWeekIndex === -1) {
                teachers = [];
                groups.clear();
                return;
            }
    
            const emploi = historique[selectedWeekIndex]?.emploiDuTemps;
            if (!emploi) {
                teachers = [];
                groups.clear();
                return;
            }
            
            teachers = Object.keys(emploi).sort();
            groups.clear();
            for (const teacher in emploi) {
                for (const day in emploi[teacher]) {
                    for (const slot in emploi[teacher][day]) {
                        const groupString = emploi[teacher][day][slot]?.groupe;
                        if (groupString) {
                            groupString.split(' ').forEach(g => g.trim() && groups.add(g.trim()));
                        }
                    }
                }
            }
        }
    
        // --- SECTION CALENDRIER ---
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
            
            document.addEventListener('click', (e) => {
                if (!display.closest('.custom-week-picker') && !popup.closest('.custom-week-picker')) {
                    popup.style.display = 'none';
                }
            });
        }
    
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
                    } else if (currentDay > daysInMonth) {
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
                        
                        if (cellDate.getMonth() === 7) { 
                            dayDiv.classList.add('disabled-day');
                        } else {
                            dayDiv.onclick = () => {
                               updateWeekSelection(cellDate);
                               document.getElementById('calendarPopup').style.display = 'none';
                            };
                        }
                        
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
    
        function updateWeekSelection(date) {
            selectedDate = date;
            const weekInfo = getWeekInfo(date);
            
            document.getElementById('weekDisplayText').innerHTML = `<strong>S${weekInfo.weekNumber}</strong> <span style="font-size:0.9em; color: #555;">(${weekInfo.startOfWeek.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' })} - ${weekInfo.endOfWeek.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' })})</span>`;
            
            if (weekInfo.weekValue !== currentWeekValue) {
                currentWeekValue = weekInfo.weekValue;
                selectedWeekIndex = historique.findIndex(w => w.semaine === currentWeekValue);
                updateAllViews();
            }
            generateCalendar(calendarCurrentDate);
        }
    
        function getSchoolYear(date) {
            return (date.getMonth() < 8) ? date.getFullYear() - 1 : date.getFullYear();
        }
    
        function getWeekInfo(date) {
            const schoolYear = getSchoolYear(date);
            let firstMonday = new Date(schoolYear, 8, 1);
            firstMonday.setDate(firstMonday.getDate() + (8 - firstMonday.getDay()) % 7);
            if (firstMonday.getDate() > 7) firstMonday.setDate(firstMonday.getDate() - 7);
            
            let startOfWeek = new Date(date);
            startOfWeek.setDate(date.getDate() - (date.getDay() + 6) % 7);
            startOfWeek.setHours(0, 0, 0, 0);
            firstMonday.setHours(0, 0, 0, 0);
            
            let weekNumber = 1;
            if (startOfWeek >= firstMonday) {
                weekNumber = Math.floor((startOfWeek - firstMonday) / (1000 * 60 * 60 * 24 * 7)) + 1;
            }
    
            const endOfWeek = new Date(startOfWeek);
            endOfWeek.setDate(startOfWeek.getDate() + 6);
            
            return {
                schoolYear, weekNumber, startOfWeek, endOfWeek,
                weekValue: `${schoolYear}-W${weekNumber}`
            };
        }
    
        // --- FONCTIONS DE RENDU ---
        function displayGlobalSchedule() {
            const table = document.getElementById('global-schedule');
            
            if (selectedWeekIndex === -1) {
                table.innerHTML = "<thead><tr><th>Aucune donnée pour cette semaine. Sélectionnez une autre semaine.</th></tr></thead>";
                return;
            }
    
            const emploi = historique[selectedWeekIndex]?.emploiDuTemps;
            if (!emploi) { table.innerHTML = "<thead><tr><th>Aucune donnée pour cette semaine</th></tr></thead>"; return; }
            
            let headerHtml = '<thead><tr><th rowspan="2" colspan="2"></th>';
            days.forEach((day, index) => headerHtml += `<th colspan="4">${day}</th>`);
            headerHtml += '</tr><tr>';
            days.forEach(() => slots.forEach(slot => headerHtml += `<th>${slot}</th>`));
            headerHtml += '</tr></thead>';
    
            let bodyHtml = '<tbody>';
            const items = (currentMode === 'formateur') ? teachers : Array.from(groups).sort();
            const infoTypes = (currentMode === 'formateur') ? ['Groupe', 'Module', 'Espace'] : ['Formateur', 'Module', 'Espace'];
            
            items.forEach(item => {
                const sessionColors = {};
                days.forEach(day => {
                    slots.forEach(slot => {
                        const key = `${day}-${slot}`;
                        let salle = '';
                        if (currentMode === 'formateur') {
                            salle = emploi[item]?.[day]?.[slot]?.salle || '';
                        } else {
                            salle = findGroupData(emploi, item, day, slot, 'salle') || '';
                        }
                        if (salle.toUpperCase() === 'TEAMS') sessionColors[key] = '#E6E6FA';
                        else if (salle.toUpperCase() === 'EFM RÉG') sessionColors[key] = '#ADD8E6';
                    });
                });
    
                for (let i = 0; i < infoTypes.length; i++) {
                    bodyHtml += `<tr ${i === infoTypes.length - 1 ? 'class="salle-row"' : ''}>`;
                    if (i === 0) bodyHtml += `<td rowspan="3" class="name-cell">${item}</td>`;
                    bodyHtml += `<td class="type-cell">${infoTypes[i]}</td>`;
                    
                    days.forEach(day => {
                        slots.forEach(slot => {
                            let data = '';
                            const propertyKey = infoTypes[i].toLowerCase() === 'espace' ? 'salle' : infoTypes[i].toLowerCase();
                            if (currentMode === 'formateur') {
                                data = emploi[item]?.[day]?.[slot]?.[propertyKey] || '';
                            } else {
                                const session = findGroupData(emploi, item, day, slot, 'all');
                                data = (propertyKey === 'formateur' ? session.formateur : session[propertyKey]) || '';
                            }
                            const key = `${day}-${slot}`;
                            const backgroundColor = sessionColors[key] || '';
                            const style = backgroundColor ? `background-color: ${backgroundColor};` : '';
                            bodyHtml += `<td style="${style}">${data}</td>`;
                        });
                    });
                    bodyHtml += `</tr>`;
                }
            });
            table.innerHTML = headerHtml + bodyHtml + '</tbody>';
        }
        
        function findGroupData(emploi, group, day, slot, infoType) {
            for (const teacher in emploi) {
                if (Object.prototype.hasOwnProperty.call(emploi, teacher)) {
                    const session = emploi[teacher]?.[day]?.[slot];
                    if (session && typeof session.groupe === 'string' && session.groupe) {
                        const sessionGroups = session.groupe.trim().split(' ').filter(g => g);
                        if (sessionGroups.includes(group)) {
                            return infoType === 'all' ? { formateur: teacher, ...session } : (infoType === 'teacher' ? teacher : (session[infoType] || ''));
                        }
                    }
                }
            }
            return infoType === 'all' ? {} : '';
        }
        
        function displayTeacherSchedule(teacher) {
            const table = document.getElementById('teacher-schedule-table');
            if (selectedWeekIndex === -1) { table.innerHTML = '<tbody><tr><td colspan="5">Aucune donnée disponible.</td></tr></tbody>'; return; }
            const emploi = historique[selectedWeekIndex]?.emploiDuTemps?.[teacher];
            if (!emploi) { table.innerHTML = '<tbody><tr><td colspan="5">Aucun emploi du temps disponible.</td></tr></tbody>'; return; }
    
            let html = `<thead><tr><th>Jour</th><th>Info</th><th>S1</th><th>S2</th><th>S3</th><th>S4</th></tr></thead><tbody>`;
            days.forEach(day => {
                const dayData = emploi[day] || {};
                html += `<tr><td rowspan="3" class="day-header">${day}</td><td class="info-type">Groupe</td><td>${dayData.S1?.groupe || '-'}</td><td>${dayData.S2?.groupe || '-'}</td><td>${dayData.S3?.groupe || '-'}</td><td>${dayData.S4?.groupe || '-'}</td></tr>
                         <tr><td class="info-type">Module</td><td>${dayData.S1?.module || '-'}</td><td>${dayData.S2?.module || '-'}</td><td>${dayData.S3?.module || '-'}</td><td>${dayData.S4?.module || '-'}</td></tr>
                         <tr><td class="info-type">Espace</td><td>${dayData.S1?.salle || '-'}</td><td>${dayData.S2?.salle || '-'}</td><td>${dayData.S3?.salle || '-'}</td><td>${dayData.S4?.salle || '-'}</td></tr>`;
            });
            table.innerHTML = html + '</tbody>';
        }
    
        function displayGroupSchedule(group) {
            const table = document.getElementById('group-schedule-table');
            if (selectedWeekIndex === -1) { table.innerHTML = '<tbody><tr><td colspan="5">Aucune donnée disponible.</td></tr></tbody>'; return; }
            const emploi = historique[selectedWeekIndex]?.emploiDuTemps;
            if (!emploi) { table.innerHTML = '<tbody><tr><td colspan="5">Aucune donnée disponible.</td></tr></tbody>'; return; }
    
            let html = `<thead><tr><th>Jour</th><th>Info</th><th>S1</th><th>S2</th><th>S3</th><th>S4</th></tr></thead><tbody>`;
            days.forEach(day => {
                html += `<tr><td rowspan="3" class="day-header">${day}</td><td class="info-type">Formateur</td><td>${findGroupData(emploi, group, day, 'S1', 'teacher') || '-'}</td><td>${findGroupData(emploi, group, day, 'S2', 'teacher') || '-'}</td><td>${findGroupData(emploi, group, day, 'S3', 'teacher') || '-'}</td><td>${findGroupData(emploi, group, day, 'S4', 'teacher') || '-'}</td></tr>
                         <tr><td class="info-type">Module</td><td>${findGroupData(emploi, group, day, 'S1', 'module') || '-'}</td><td>${findGroupData(emploi, group, day, 'S2', 'module') || '-'}</td><td>${findGroupData(emploi, group, day, 'S3', 'module') || '-'}</td><td>${findGroupData(emploi, group, day, 'S4', 'module') || '-'}</td></tr>
                         <tr><td class="info-type">Espace</td><td>${findGroupData(emploi, group, day, 'S1', 'salle') || '-'}</td><td>${findGroupData(emploi, group, day, 'S2', 'salle') || '-'}</td><td>${findGroupData(emploi, group, day, 'S3', 'salle') || '-'}</td><td>${findGroupData(emploi, group, day, 'S4', 'salle') || '-'}</td></tr>`;
            });
            table.innerHTML = html + '</tbody>';
        }
    
        function setupAutocomplete(type) {
            const input = document.getElementById(`${type}-search`);
            const list = document.getElementById(`${type}-autocomplete-list`);
            input.addEventListener('input', () => {
                list.style.display = 'block';
                const term = input.value.toLowerCase();
                Array.from(list.children).forEach(item => item.style.display = item.textContent.toLowerCase().includes(term) ? 'block' : 'none');
            });
            document.addEventListener('click', e => { if (!e.target.closest('.autocomplete-container')) list.style.display = 'none'; });
        }
    
        function updateAutocompleteLists() {
            populateAutocomplete('teacher', teachers);
            populateAutocomplete('group', Array.from(groups).sort());
        }
    
        function populateAutocomplete(type, dataArray) {
            const list = document.getElementById(`${type}-autocomplete-list`);
            list.innerHTML = '';
            dataArray.forEach(itemValue => {
                const item = document.createElement('div');
                item.className = 'autocomplete-item';
                item.textContent = itemValue;
                item.onclick = () => {
                    document.getElementById(`${type}-search`).value = itemValue;
                    list.style.display = 'none';
                    if (type === 'teacher') { selectedTeacher = itemValue; displayTeacherSchedule(itemValue); } 
                    else { selectedGroup = itemValue; displayGroupSchedule(itemValue); }
                };
                list.appendChild(item);
            });
        }
    
        function loadImageAsBase64(url) {
            return new Promise((resolve, reject) => {
                const img = new Image();
                img.crossOrigin = 'Anonymous';
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    canvas.width = img.width;
                    canvas.height = img.height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0);
                    const dataURL = canvas.toDataURL('image/png');
                    resolve(dataURL);
                };
                img.onerror = (error) => reject(new Error('Failed to load image: ' + url));
                img.src = url;
            });
        }
    
        // --- FONCTIONS PDF ---
        async function downloadGlobalPDF(mode) {
        const loadingOverlay = document.getElementById('pdf-loading-overlay');
        const loadingMessage = document.getElementById('pdf-loading-message');
        const weekData = historique[selectedWeekIndex];
        const emploi = weekData?.emploiDuTemps;
    
        if (!emploi || Object.keys(emploi).length === 0) {
            alert("Aucune donnée d'emploi du temps à exporter pour cette semaine.");
            return;
        }
    
        loadingMessage.textContent = `Génération du PDF Global (${mode})...`;
        loadingOverlay.style.display = 'flex';
    
        try {
            const logoBase64 = await loadImageAsBase64('assets/images/logo_ofppt.png');
    
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a2' });
                const M = (mode === 'formateur') ? 'FORMATEUR' : 'GROUPE';
    
            doc.addImage(logoBase64, 'PNG', 15, 10, 30, 30);
            doc.setFontSize(20);
            doc.setFont('helvetica', 'bold');
            doc.text(`EMPLOI GLOBAL ${M}S`, doc.internal.pageSize.width / 2, 22, { align: 'center' });
            const { dateDebut, dateFin } = getDatesForISOWeek(weekData.semaine);
            const startDate = formatDate(dateDebut);
            doc.setFontSize(16);
            doc.setFont('helvetica', 'normal');
            doc.text(`A PARTIR DU : ${startDate}`, 265, 38);
    
            const timeHeaders = ['08h30 11h00', '11h00 13h30', '13h30 16h00', '16h00 18h30'];
            const head = [
                [
                    { content: 'ÉMARGEMENT', rowSpan: 2, styles: { halign: 'center', fontStyle: 'bold', fillColor: [255, 255, 255], textColor: [0,0,0] } },
                    { content: `${M}`, rowSpan: 2, styles: { halign: 'center', fontStyle: 'bold', fillColor: [255, 255, 255], textColor: [0,0,0] } },
                    { content: 'TYPE', rowSpan: 2, styles: { halign: 'center', fontStyle: 'bold', fillColor: [255, 255, 255], textColor: [0,0,0] } }
                ],
                []
            ];
            
            days.forEach(day => {
                head[0].push({ content: day.toUpperCase(), colSpan: 4, styles: { halign: 'center', fillColor: '#fff', textColor: [0, 0, 0], fontStyle: 'bold' } });
                timeHeaders.forEach(time => head[1].push({ content: time, styles: { halign: 'center', fillColor: '#fff', textColor: [0, 0, 0] } }));
            });
            
            const body = [];
            const items = (mode === 'formateur') ? teachers : Array.from(groups).sort();
            const slots = ['S1', 'S2', 'S3', 'S4'];
            const infoTypes = (mode === 'formateur') ? ['Groupe', 'Module', 'Salle'] : ['Formateur', 'Module', 'Salle'];
    
            items.forEach(item => {
                const rowGroup = [ [], [], [] ];
                rowGroup[0].unshift({ content: '', rowSpan: 3 });
                rowGroup[0].push({ content: item, rowSpan: 3, styles: { valign: 'middle', fontStyle: 'bold' } });
                rowGroup[0].push({ content: infoTypes[0].toUpperCase(), styles: { fontStyle: 'bold' } });
                rowGroup[1].push({ content: infoTypes[1].toUpperCase() });
                rowGroup[2].push({ content: infoTypes[2].toUpperCase() });
    
                days.forEach(day => {
                    slots.forEach(slot => {
                        let sessionData = {};
                        if (mode === 'formateur') sessionData = emploi[item]?.[day]?.[slot] || {};
                        else sessionData = findGroupData(emploi, item, day, slot, 'all') || {};
                        const cleanContent = (data) => (typeof data === 'object' && data !== null) ? '' : (data || '');
                        const content1 = (mode === 'formateur') ? cleanContent(sessionData.groupe) : cleanContent(sessionData.formateur);
                        const content2 = cleanContent(sessionData.module);
                        const content3 = cleanContent(sessionData.salle);
                        const style = content1 ? { fillColor: [240, 240, 0] } : {}; 
                        rowGroup[0].push({ content: content1, styles: style });
                        rowGroup[1].push({ content: content2, styles: style });
                        rowGroup[2].push({ content: content3, styles: style });
                    });
                });
                body.push(...rowGroup);
            });
    
            doc.autoTable({
                startY: 45, head: head, body: body, theme: 'grid',
                styles: { fontSize: 10, cellPadding: 2, halign: 'center', valign: 'middle', lineWidth: 0.1, lineColor: [0, 0, 0] },
                headStyles: { lineWidth: 0.1, lineColor: [0, 0, 0], fontStyle: 'bold' },
                columnStyles: { 0: { cellWidth: 32 }, 1: { cellWidth: 28 }, 2: { cellWidth: 28, fontStyle: 'bold' } },
                didParseCell: (data) => {
                    if (data.column.index === 0 && data.row.index % 3 !== 0) data.cell.text = '';
                }
            });
            doc.save(`EDT_GLOBAL_${mode.toUpperCase()}_${weekData.semaine}.pdf`);
        } catch (error) {
            console.error('Erreur PDF Global:', error);
            alert('Erreur lors de la génération du PDF Global: ' + error.message);
        } finally {
            loadingOverlay.style.display = 'none';
        }
    }
    
    function getDatesForISOWeek(weekString) {
        if (!weekString) return { dateDebut: '', dateFin: ''};
        const [year, week] = weekString.split('-W').map(Number);
        const simple = new Date(year, 0, 1 + (week - 1) * 7);
        const dow = simple.getDay();
        const isoWeekStart = new Date(simple);
        isoWeekStart.setDate(simple.getDate() - (dow === 0 ? 6 : dow - 1) + 1);
        const isoWeekEnd = new Date(isoWeekStart);
        isoWeekEnd.setDate(isoWeekStart.getDate() + 5);
        return { dateDebut: isoWeekStart.toISOString().split('T')[0], dateFin: isoWeekEnd.toISOString().split('T')[0] };
    }
    function formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        if (isNaN(date)) return '';
        const day = String(date.getUTCDate()).padStart(2, '0');
        const month = String(date.getUTCMonth() + 1).padStart(2, '0');
        const year = date.getUTCFullYear();
        return `${day}/${month}/${year}`;
    }
    async function downloadPDF(type) {
                const loadingOverlay = document.getElementById('pdf-loading-overlay');
                const loadingMessage = document.getElementById('pdf-loading-message');
                
                if ((type === 'teacher' && !selectedTeacher) || (type === 'group' && !selectedGroup)) {
                    alert(`Veuillez sélectionner un ${type === 'teacher' ? 'formateur' : 'groupe'}`);
                    return;
                }
                
                loadingMessage.textContent = `Génération du PDF pour le ${type}...`;
                loadingOverlay.style.display = 'flex';
    
                try {
                    const logoBase64 = await loadImageAsBase64('assets/images/logo_ofppt.png');
                    const { jsPDF } = window.jspdf;
                    const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
                    doc.addImage(logoBase64, 'png', 13, 8, 22, 22);
    
                    doc.setFontSize(11);
                    doc.setFont('helvetica', 'normal');
                    doc.text("Direction : DRCS", 14, 38);
                    doc.text("Complexe/EFP : CFP-BEN M'SIK/ ISTA IBM", 14, 44);
                    
                    if (type === 'group') {
                        const AF = selectedGroup.slice(-3) < 200 ? "1A" : "2A";
                        doc.text(`Année de formation : ${AF}`, 14, 50);
                    }
    
                    doc.setFontSize(16);
                    doc.setFont('helvetica', 'bold');
                    doc.text(`EMPLOI DU TEMPS ${type.toUpperCase()}`, 160, 24, { align: 'center' });
                    doc.text("Au Titre de l'année 2024-2025", 160, 31, { align: 'center' });
                    
                    let masseHoraire = 0;
                    const dureeCreneaux = { 'S1': 2.5, 'S2': 2.5, 'S3': 2.5, 'S4': 2.5 };
                    const emploi = historique[selectedWeekIndex].emploiDuTemps;
                    
                    if (type === 'teacher') {
                        const teacherEmploi = emploi[selectedTeacher];
                        for (const day in teacherEmploi) {
                            for (const creneauId in teacherEmploi[day]) {
                                if (teacherEmploi[day][creneauId]?.groupe) masseHoraire += dureeCreneaux[creneauId] || 0;
                            }
                        }
                        doc.setFontSize(16);
                        doc.setFont('helvetica', 'bold');
                        doc.text(`Formateur : ${selectedTeacher}`, 250, 55, { align: 'center' });
                    } else {
                        for (const formateur in emploi) {
                            for (const jour in emploi[formateur]) {
                                for (const creneauId in emploi[formateur][jour]) {
                                    const cours = emploi[formateur][jour][creneauId];
                                    if (cours.groupe && cours.groupe.split(' ').includes(selectedGroup)) {
                                        masseHoraire += dureeCreneaux[creneauId] || 0;
                                    }
                                }
                            }
                        }
                        doc.text(`Groupe : ${selectedGroup}`, 250, 55, { align: 'center' });
                    }
                    
                    doc.setFontSize(11);
                    doc.setFont('helvetica', 'normal');
                    doc.text(`Masse Horaire : ${Math.round(masseHoraire * 100) / 100} H`, 14, 56);
                    const week = historique[selectedWeekIndex];
                    const weeklabel1 = week.semaine.replace('2025-', '').replace('W', 'S');
                    const { dateDebut, dateFin } = getDatesForISOWeek(week.semaine);
                    doc.text(`Période d'application: ${weeklabel1} (${formatDate(dateDebut)} - ${formatDate(dateFin)})`, 112, 56);
                    
                    const timeSlots = [
                        { id: 'S1', label: '8H30-11H' }, { id: 'S2', label: '11H-13H30' },
                        { id: 'S3', label: '13H30-16H' }, { id: 'S4', label: '16H-18H30' }
                    ];
    
                    const initialTableData = [];
                    days.forEach(day => {
                        const info1Row = [day, type === 'teacher' ? 'Groupe' : 'Formateur'];
                        const info2Row = ['', 'Module'];
                        const info3Row = ['', 'Espace'];
    
                        timeSlots.forEach(slot => {
                            let content1 = '', content2 = '', content3 = '';
                            if (type === 'teacher') {
                                const cours = emploi[selectedTeacher]?.[day]?.[slot.id];
                                content1 = cours?.groupe || ''; content2 = cours?.module || ''; content3 = cours?.salle || '';
                            } else {
                                for (const teacher in emploi) {
                                    const cours = emploi[teacher]?.[day]?.[slot.id];
                                    if (cours?.groupe && cours.groupe.split(' ').includes(selectedGroup)) {
                                        content1 = teacher; content2 = cours.module; content3 = cours.salle;
                                        break;
                                    }
                                }
                            }
                            info1Row.push(content1); info2Row.push(content2); info3Row.push(content3);
                        });
                        initialTableData.push(info1Row, info2Row, info3Row);
                    });
    
                    for (let dayIndex = 0; dayIndex < days.length; dayIndex++) {
                        const startRow = dayIndex * 3;
                        for (let timeIndex = 0; timeIndex < timeSlots.length; timeIndex++) {
                            const colIndex = 2 + timeIndex;
                            const isEmpty = !initialTableData[startRow][colIndex] && !initialTableData[startRow + 1][colIndex] && !initialTableData[startRow + 2][colIndex];
                            if (isEmpty) {
                                initialTableData[startRow][colIndex] = { content: '', rowSpan: 3 };
                                initialTableData[startRow + 1][colIndex] = null;
                                initialTableData[startRow + 2][colIndex] = null;
                            }
                        }
                    }
                    // (Le reste de la logique de fusion et de génération PDF reste identique)
    
                    doc.autoTable({
                        startY: 65,
                        head: [['', '', '8H30-11H', '11H-13H30', '13H30-16H', '16H-18H30']],
                        body: initialTableData, // Utilisation des données préparées
                        theme: 'grid',
                        // ... (styles et hooks didDrawCell/didParseCell comme avant)
                    });
                    
                    doc.save(`EDT_${type.toUpperCase()}_${type === 'teacher' ? selectedTeacher : selectedGroup}_${week.semaine}.pdf`);
    
                } catch (error) {
                    console.error('Erreur PDF:', error);
                    alert('Erreur lors de la génération du PDF: ' + error.message);
                } finally {
                    loadingOverlay.style.display = 'none';
                }
            }
    
    
            function displayDefaultSpecificView() {
                updateDataForWeek();
                updateAutocompleteLists();
                const sortedGroups = Array.from(groups).sort();
    
                if (currentMode === 'formateur') {
                    document.getElementById('group-schedule-table').innerHTML = '';
                    selectedGroup = '';
                    document.getElementById('group-search').value = '';
                    if (teachers.length > 0) {
                        selectedTeacher = teachers[0];
                        document.getElementById('teacher-search').value = teachers[0];
                        displayTeacherSchedule(teachers[0]);
                    } else {
                        selectedTeacher = ''; document.getElementById('teacher-search').value = '';
                        document.getElementById('teacher-schedule-table').innerHTML = '<tbody><tr><td colspan="6">Aucun formateur disponible.</td></tr></tbody>';
                    }
                } else {
                    document.getElementById('teacher-schedule-table').innerHTML = '';
                    selectedTeacher = '';
                    document.getElementById('teacher-search').value = '';
                    if (sortedGroups.length > 0) {
                        selectedGroup = sortedGroups[0];
                        document.getElementById('group-search').value = sortedGroups[0];
                        displayGroupSchedule(sortedGroups[0]);
                    } else {
                        selectedGroup = ''; document.getElementById('group-search').value = '';
                        document.getElementById('group-schedule-table').innerHTML = '<tbody><tr><td colspan="6">Aucun groupe disponible.</td></tr></tbody>';
                    }
                }
            }
        
        // --- ASSISTANT VOCAL ---
        const voiceAssistantBtn = document.getElementById('voiceAssistantBtn');
        const assistantStatus = document.getElementById('assistantStatus');
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        let recognition;
    
        if (SpeechRecognition) {
            recognition = new SpeechRecognition();
            recognition.continuous = false;
            recognition.lang = 'fr-FR';
            recognition.interimResults = false;
            setupVoiceAssistant();
        } else {
            voiceAssistantBtn.style.display = 'none';
        }
    
        function setupVoiceAssistant() {
            voiceAssistantBtn.addEventListener('click', toggleListening);
            recognition.onresult = (event) => {
                const transcript = event.results[event.results.length - 1][0].transcript.trim();
                updateStatus(`J'ai entendu : "${transcript}"`);
                processCommand(transcript);
            };
            recognition.onerror = () => {
                updateStatus("Désolé, je n'ai pas compris.", 3000, true);
                stopListening();
            };
            recognition.onend = () => stopListening();
        }
        
        function toggleListening() {
            if (voiceAssistantBtn.classList.contains('listening')) recognition.stop();
            else startListening();
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
            if (duration > 0) setTimeout(() => assistantStatus.classList.remove('visible'), duration);
        }
        
        function speak(text) {
            speechSynthesis.cancel();
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = 'fr-FR';
            utterance.rate = 1.1;
            speechSynthesis.speak(utterance);
        }
    
        async function processCommand(command) {
            updateStatus('Analyse en cours...', 0);
            const system_prompt = `
                Tu es un assistant pour une page d'édition d'emplois du temps. Convertis la requête en JSON.
                Contexte: Formateurs valides: ${teachers.map(t=>t.toUpperCase()).join(', ')}. Groupes valides: ${Array.from(groups).map(g=>g.toUpperCase()).join(', ')}.
                Intents: 'telecharger_pdf', 'changer_vue', 'changer_mode', 'selectionner_semaine', 'naviguer_page', 'inconnue'.
                Format de réponse JSON OBLIGATOIRE: {"intent": "...", "parameters": {"cible": "...", "nom": "...", "vue": "...", "mode": "...", "semaine": ..., "page": "..."}, "response": "..."}
                Exemples:
                - "Télécharge le PDF pour Dupont" -> {"intent": "telecharger_pdf", "parameters": {"cible": "formateur", "nom": "DUPONT"}, "response": "Je prépare le PDF pour Dupont."}
                - "Exporte le global des groupes" -> {"intent": "telecharger_pdf", "parameters": {"cible": "global", "mode": "groupe"}, "response": "PDF global pour les groupes en cours."}
                Analyse: "${command}"
            `;
            try {
                // On appelle notre propre proxy au lieu de l'API Google directement
                const response = await fetch('../api/data/gemini_proxy.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ prompt: system_prompt }) // On envoie le prompt au serveur
                });
    
                if (!response.ok) throw new Error('Réponse du proxy non valide.');
    
                const data = await response.json();
                // La réponse du proxy contient directement le texte de l'IA
                const rawText = data.candidates[0].content.parts[0].text;
                const result = JSON.parse(rawText.replace(/```json|```/g, '').trim());
                speak(result.response);
                switch (result.intent) {
                    case 'telecharger_pdf': handleDownloadCommand(result.parameters); break;
                    case 'changer_vue': if ((result.parameters.vue === 'detaillee' && currentView === 'global') || (result.parameters.vue === 'globale' && currentView === 'specific')) toggleView(); break;
                    case 'changer_mode': if (result.parameters.mode === 'formateur') document.getElementById('tab1').click(); else if (result.parameters.mode === 'groupe') document.getElementById('tab2').click(); break;
                    case 'selectionner_semaine': if (result.parameters.semaine) { const schoolYear = getSchoolYear(new Date()); let firstMonday = getWeekInfo(new Date(schoolYear, 8, 1)).startOfWeek; let targetDate = new Date(firstMonday); targetDate.setDate(firstMonday.getDate() + (result.parameters.semaine - 1) * 7); updateWeekSelection(targetDate); } break;
                    case 'naviguer_page': const pageMap = {'accueil': 'emploi.html', 'edition': 'edition.html', 'avancement': 'avancement.html', 'espaces': 'espaces.html'}; if (result.parameters.page && pageMap[result.parameters.page]) window.location.href = pageMap[result.parameters.page]; break;
                }
            } catch (error) {
                console.error("Erreur Gemini:", error);
                speak("Désolé, une erreur est survenue.");
                updateStatus("Erreur de communication", 4000, true);
            }
        }
        
        function handleDownloadCommand(params) {
            const { cible, nom, mode } = params;
            if (cible === 'global') {
                if (mode && mode !== currentMode) switchMode(mode);
                if (currentView !== 'global') toggleView();
                setTimeout(() => document.getElementById('download-global-pdf-btn').click(), 300);
            } else if (cible === 'formateur' || cible === 'groupe') {
                if (!nom) { speak("Veuillez spécifier un nom."); return; }
                if (currentView !== 'specific') toggleView();
                switchMode(cible);
                setTimeout(() => {
                    const searchInput = document.getElementById(`${cible}-search`);
                    const list = document.getElementById(`${cible}-autocomplete-list`);
                    searchInput.value = nom;
                    const item = Array.from(list.children).find(c => c.textContent.toUpperCase() === nom.toUpperCase());
                    if (item) {
                        item.click();
                        setTimeout(() => document.getElementById(`download-${cible}-pdf`).click(), 500);
                    } else {
                        speak(`Désolé, je ne trouve pas le ${cible} nommé ${nom}.`);
                    }
                }, 500);
            }
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
            loadData();
        } catch (error) {
            console.error('Session check failed:', error);
            window.location.href = 'login.html';
        }
    }
    document.addEventListener('DOMContentLoaded', checkSessionAndLoad);