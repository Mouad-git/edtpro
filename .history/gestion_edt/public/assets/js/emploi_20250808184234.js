const jours=["Lundi","Mardi","Mercredi","Jeudi","Vendredi","Samedi"],seances=["S1","S2","S3","S4"],SEANCE_DURATION=2.5;let autoGenConstraints={};const dayClasses={Lundi:"lundi-cell",Mardi:"mardi-cell",Mercredi:"mercredi-cell",Jeudi:"jeudi-cell",Vendredi:"vendredi-cell",Samedi:"samedi-cell"};let appData={formateurs:[],groupes:[],fusionGroupes:[],espaces:[],affectations:[]},timetableData={},groupHours={},clipboard={type:null,data:[]},selectedDate=new Date,calendarCurrentDate=new Date,currentWeekValue="",groupHoursChart=null;function updateEspacesTags(){let e=document.getElementById("espacesContainer");e&&(e.innerHTML="",appData.espaces.forEach(t=>{let a=document.createElement("div");a.className="espace-tag",a.innerHTML=`${t}<button onclick="removeEspace('${t}')">\xd7</button>`,e.appendChild(a)}))}function setupCustomCalendar(){let e=document.getElementById("customWeekDisplay"),t=document.getElementById("calendarPopup"),a=document.getElementById("prevMonthBtn"),r=document.getElementById("nextMonthBtn");e.addEventListener("click",()=>{let e="block"===t.style.display;t.style.display=e?"none":"block",e||generateCalendar(calendarCurrentDate=new Date(selectedDate))}),a.addEventListener("click",()=>{calendarCurrentDate.setMonth(calendarCurrentDate.getMonth()-1),generateCalendar(calendarCurrentDate)}),r.addEventListener("click",()=>{calendarCurrentDate.setMonth(calendarCurrentDate.getMonth()+1),generateCalendar(calendarCurrentDate)}),document.addEventListener("click",a=>{e.contains(a.target)||t.contains(a.target)||(t.style.display="none")})}function generateCalendar(e){let t=document.getElementById("calendarBody"),a=document.getElementById("calendarTitle");t.innerHTML="";let r=e.getMonth(),o=e.getFullYear(),l=e.toLocaleDateString("fr-FR",{month:"long"});a.textContent=`${l.charAt(0).toUpperCase()+l.slice(1)} ${o}`;let n=new Date(o,r,1),s=new Date(o,r+1,0).getDate(),i=(n.getDay()+6)%7,c=getWeekInfo(selectedDate),{startOfWeek:u,endOfWeek:d}=c,p=1;for(let f=0;f<6;f++){let m=document.createElement("tr");for(let g=0;g<7;g++){let h=document.createElement("td");if(0===f&&g<i);else if(p>s);else{let b=new Date(o,r,p);b.setHours(0,0,0,0);let y=document.createElement("div");y.className="calendar-day",y.textContent=p;let $=new Date;$.setHours(0,0,0,0),b.getTime()===$.getTime()&&y.classList.add("today"),7===b.getMonth()?y.classList.add("disabled-day"):y.onclick=()=>{updateWeekSelection(b),document.getElementById("calendarPopup").style.display="none"},b>=u&&b<=d&&(b.getTime()===u.getTime()?y.classList.add("selected-start"):b.getTime()===d.getTime()?y.classList.add("selected-end"):y.classList.add("selected-in-range")),h.appendChild(y),p++}m.appendChild(h)}if(t.appendChild(m),p>s)break}}function updateWeekSelection(e){selectedDate=e;let t=getWeekInfo(e);document.getElementById("customWeekDisplay");let a=t.startOfWeek.toLocaleDateString("fr-FR",{day:"2-digit",month:"short"}),r=t.endOfWeek.toLocaleDateString("fr-FR",{day:"2-digit",month:"short"});document.getElementById("weekDisplayText").innerHTML=`<strong>S${t.weekNumber}</strong> <span style="font-size:0.9em; color: #555;">(${a} - ${r})</span>`,t.weekValue!==currentWeekValue&&loadWeekData(currentWeekValue=t.weekValue),generateCalendar(calendarCurrentDate)}function getSchoolYear(e){return 8>e.getMonth()?e.getFullYear()-1:e.getFullYear()}function getWeekInfo(e){let t=getSchoolYear(e),a=new Date(t,8,1),r=a.getDay(),o=new Date(a);1!==r&&o.setDate(a.getDate()+(0===r?1:8-r));let l=new Date(e),n=l.getDay(),s=l.getDate()-n+(0===n?-6:1);l.setDate(s),l.setHours(0,0,0,0),o.setHours(0,0,0,0);let i=1;if(l>=o){let c=l.getTime()-o.getTime();i=Math.floor(c/6048e5)+1}let u=new Date(l);return u.setDate(l.getDate()+6),{schoolYear:t,weekNumber:i,startOfWeek:l,endOfWeek:u,weekValue:`${t}-W${i}`}}function resizeTextToFit(e){let t=e.closest("td");if(!t)return;e.style.fontSize="10px";let a=10;for(;e.scrollWidth>t.offsetWidth&&a>6;)a-=.5,e.style.fontSize=a+"px"}function getFormattedName(e){if(!e)return"";let t=e.trim().split(/\s+/).filter(e=>e.length>0);if(t.length<=1)return e;if(2===t.length)return t[1];let a=t[t.length-2],r=t[t.length-1];return a.length<4?`${a} ${r}`:r}function handleFileUpload(e){let t=e.target.files[0];if(!t)return;document.getElementById("fileName").textContent=t.name,document.getElementById("fileName").style.display="block";let a=new FormData;a.append("excelFile",t),document.getElementById("weekLoader").style.display="flex",fetch("../api/data/upload_base_data.php",{method:"POST",body:a}).then(e=>e.ok?e.json():e.text().then(t=>{let a=t.length>300?t.substring(0,300)+"...":t;throw Error(`Erreur serveur (HTTP ${e.status}): ${a}`)})).then(e=>{document.getElementById("weekLoader").style.display="none",e.success?(showNotification("Fichier trait\xe9 et donn\xe9es mises \xe0 jour !","success"),loadInitialData()):showNotification(e.message||"Erreur lors du traitement.","error")}).catch(e=>{document.getElementById("weekLoader").style.display="none",console.error("Erreur d\xe9taill\xe9e de l'upload:",e),e instanceof SyntaxError?showNotification("Erreur: La r\xe9ponse du serveur n'est pas un JSON valide. V\xe9rifiez les logs PHP.","error"):showNotification(e.message||"Erreur de communication lors de l'envoi.","error")})}function generateTimetable(){let e=document.querySelector("#timetable tbody");if(e.innerHTML="",!appData.formateurs||0===appData.formateurs.length){e.innerHTML='<tr><td colspan="26" style="text-align:center; padding: 20px; font-size: 1.1rem; color: #777;">Aucun formateur \xe0 afficher. Veuillez charger un fichier Excel contenant les donn\xe9es de base.</td></tr>';return}renderTimetableFromData(timetableData);let t=document.getElementById("timetable");t.removeEventListener("change",handleTableInteraction),t.addEventListener("change",handleTableInteraction)}function handleTableInteraction(e){let t=e.target;if("SELECT"!==t.tagName)return;let a=t.dataset.type;"groupe"===a?(handleGroupeChange(e),updateFormateurCell(t.dataset.formateur)):"module"===a?handleModuleChange(e):"salle"===a&&handleSalleChange(e);let r=t.closest("td");t.value?r.classList.add("selected-cell"):r.classList.remove("selected-cell"),resizeTextToFit(t)}function createSelect(e,t,a,r,o,l=""){let n=document.createElement("select");n.dataset.jour=e,n.dataset.seance=t,n.dataset.formateur=a,n.dataset.type=r,n.className="auto-resize-select";let s=document.createElement("option");return s.value="",s.textContent="",n.appendChild(s),o.forEach(e=>{let t=document.createElement("option");t.value=e,t.textContent=e,e===l&&(t.selected=!0),n.appendChild(t)}),"module"===r&&(n.disabled=!l),n}function updateGroupeSelectOptions(e,t,a,r,o){let l=document.querySelector(`select[data-formateur="${e}"][data-jour="${t}"][data-seance="${a}"][data-type="groupe"]`);if(!l)return;let n=l.value;l.innerHTML="";let s=document.createElement("option");s.value="",l.appendChild(s);let i=o?appData.fusionGroupes:appData.groupes,c=o?"synchrone":"presentiel",u=!1;if(i.forEach(t=>{if(appData.affectations.some(a=>a.formateur===e&&a.groupe===t&&a.type===c)){let a=document.createElement("option");a.value=t,a.textContent=t,l.appendChild(a),t===n&&(u=!0)}}),u)l.value=n;else if(n){let d=document.createElement("option");d.value=n,d.textContent=n,d.style.color="red",l.appendChild(d),l.value=n}}async function handleGroupeChange(e){let t=e.target,a=t.dataset.jour,r=t.dataset.seance,o=t.dataset.formateur,l=t.value,n=checkGroupConflict(a,r,l,t);if(n){showNotification(`Conflit: Le groupe ${l} est d\xe9j\xe0 pris.`,"chevauchement"),highlightConflict(a,r,"groupe",l),t.value="",updateTimetableData(a,r,o,"groupe",""),t.dispatchEvent(new Event("change"));return}let s=timetableData[o][a][r].groupe;document.querySelector(`select[data-formateur="${o}"][data-jour="${a}"][data-seance="${r}"][data-type="module"]`),l?(s&&s!==l&&(groupHours[s]=(groupHours[s]||0)-2.5,checkGroupHours(s)),groupHours[l]=(groupHours[l]||0)+2.5,checkGroupHours(l)):s&&(groupHours[s]=(groupHours[s]||0)-2.5,checkGroupHours(s)),updateTimetableData(a,r,o,"groupe",l),updateModuleOptions(o,a,r,l),updateFormateurCell(o)}function copySessionData(e,t,a,r){let o=timetableData[e][t][a];timetableData[e][t][r]={...o};let l={groupe:document.querySelector(`select[data-formateur="${e}"][data-jour="${t}"][data-seance="${r}"][data-type="groupe"]`),module:document.querySelector(`select[data-formateur="${e}"][data-jour="${t}"][data-seance="${r}"][data-type="module"]`),salle:document.querySelector(`select[data-formateur="${e}"][data-jour="${t}"][data-seance="${r}"][data-type="salle"]`)};l.groupe&&(l.groupe.value=o.groupe,l.groupe.dispatchEvent(new Event("change"))),setTimeout(()=>{l.module&&(l.module.value=o.module,l.module.dispatchEvent(new Event("change"))),l.salle&&(l.salle.value=o.salle,l.salle.dispatchEvent(new Event("change"))),updateFormateurCell(e)},50)}function checkGroupHours(e){if(!e)return;let t=groupHours[e]||0;updateGroupCells(e),t>=40&&(showNotification(`Le groupe ${e} a atteint ${t.toFixed(1)}h.`,"warning"),document.querySelectorAll(`select[data-type="groupe"] option[value="${e}"]:checked`).forEach(e=>{let t=e.closest("td");t.classList.add("group-highlight"),setTimeout(()=>t.classList.remove("group-highlight"),3e3)}))}function updateGroupCells(e){if(!e)return;let t=groupHours[e]||0;document.querySelectorAll(`select[data-type="groupe"] option[value="${e}"]:checked`).forEach(e=>{let a=e.closest("td");a&&a.classList.toggle("group-highlight",t>=40)})}function getCurrentTimetableData(){return{semaine:currentWeekValue,emploiDuTemps:timetableData}}function updateFormateurCell(e){let t=document.querySelector(`td.formateur-cell[data-formateur="${e}"]`);if(!t)return;let a=0;if(timetableData[e])for(let r in timetableData[e])for(let o in timetableData[e][r])timetableData[e][r][o].groupe&&a++;let l=2.5*a,n=document.getElementById(`hours-${e}`);n&&(n.textContent=`${l.toFixed(1)}`),t.classList.remove("formateur-green","formateur-orange","formateur-red"),l<=22.5?t.classList.add("formateur-green"):l<=35?t.classList.add("formateur-orange"):t.classList.add("formateur-red")}function handleModuleChange(e){let t=e.target,a=t.dataset.jour,r=t.dataset.seance,o=t.dataset.formateur,l=t.value,n=t.parentElement.querySelector(".custom-select-display");n&&(n.textContent=l),updateTimetableData(a,r,o,"module",l);let s=t.closest("td");if(s){if(s.classList.remove("module-s1","module-s2","module-annual","module-regional"),l){let i=t.options[t.selectedIndex],c=i.dataset.semester,u="true"===i.dataset.regional;c&&s.classList.add(`module-${c.toLowerCase()}`),u&&s.classList.add("module-regional"),s.classList.add("selected-cell")}else s.classList.remove("selected-cell")}}function handleSalleChange(e){let t=e.target,a=t.dataset.jour,r=t.dataset.seance,o=t.dataset.formateur,l=t.value,n=t.closest("td");if(n.classList.toggle("selected-cell",!!l),l&&checkRoomConflict(a,r,l,t)){showNotification(`La salle ${l} est d\xe9j\xe0 occup\xe9e.`,"chevauchement"),highlightConflict(a,r,"salle",l),t.value="",n.classList.remove("selected-cell"),updateTimetableData(a,r,o,"salle","");return}updateGroupeSelectOptions(o,a,r,l,"TEAMS"===l);let s=document.querySelector(`select[data-jour="${a}"][data-seance="${r}"][data-formateur="${o}"][data-type="groupe"]`);if(s){let i=Array.from(s.options).map(e=>e.value);i.includes(s.value)||(s.value="",s.dispatchEvent(new Event("change")))}updateTimetableData(a,r,o,"salle",l)}function checkGroupConflict(e,t,a,r){if(!a)return!1;let o=a.trim().split(/\s+/).filter(e=>e),l=document.querySelectorAll(`select[data-jour="${e}"][data-seance="${t}"][data-type="groupe"]`);for(let n of l){if(n===r||!n.value)continue;let s=n.value.trim().split(/\s+/).filter(e=>e);if(o.some(e=>s.includes(e)))return!0}return!1}function checkRoomConflict(e,t,a,r){if("TEAMS"===a||!a)return!1;let o=document.querySelectorAll(`select[data-jour="${e}"][data-seance="${t}"][data-type="salle"]`);for(let l of o)if(l!==r&&l.value===a)return!0;return!1}function highlightConflict(e,t,a,r){document.querySelectorAll(`select[data-jour="${e}"][data-seance="${t}"][data-type="${a}"]`).forEach(e=>{let t=e.value.trim().split(/\s+/);if(t.includes(r)){let a=e.closest("td");a.classList.add("conflict-highlight"),setTimeout(()=>a.classList.remove("conflict-highlight"),3e3)}})}function initializeTimetableData(){timetableData={},appData.formateurs.forEach(e=>{timetableData[e]={},jours.forEach(t=>{timetableData[e][t]={},seances.forEach(a=>{timetableData[e][t][a]={groupe:"",module:"",salle:""}})})}),groupHours={}}function updateTimetableData(e,t,a,r,o){timetableData[a]&&timetableData[a][e]&&timetableData[a][e][t]&&(timetableData[a][e][t][r]=o)}async function saveTimetable(){let e=currentWeekValue;if(!e){showNotification("Veuillez s\xe9lectionner une semaine.","warning");return}let t=getCurrentTimetableData();try{let a=await fetch("../api/data/save_timetable.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(t)}),r=await a.json();r.success?showNotification("Emploi du temps enregistr\xe9 avec succ\xe8s","success"):showNotification("Erreur: "+(r.message||"Erreur serveur"),"error")}catch(o){showNotification("Erreur r\xe9seau: "+o.message,"error")}}function resetTimetable(){confirm("\xcates-vous s\xfbr de vouloir effacer l'emploi du temps affich\xe9 ?")&&(initializeTimetableData(),renderTimetableFromData(timetableData),showNotification("L'emploi du temps a \xe9t\xe9 effac\xe9.","info"))}Chart.register(ChartDataLabels),document.addEventListener("DOMContentLoaded",function(){document.getElementById("notificationClose").addEventListener("click",()=>{let e=document.getElementById("notification");e.classList.remove("show"),e.classList.add("hide")})}),window.addEventListener("resize",()=>{document.querySelectorAll(".auto-resize-select").forEach(resizeTextToFit)}),document.getElementById("saveTimetableBtn")?.addEventListener("click",saveTimetable);let notificationTimeout;function showNotification(e,t="info"){let a=document.getElementById("notification"),r=document.getElementById("notificationHeader"),o=document.getElementById("notificationText"),l=document.getElementById("notificationIcon");clearTimeout(notificationTimeout),a.classList.remove("show","hide");let n={success:{header:"Succ\xe8s",icon:"fa-check",color:"#4CAF50"},error:{header:"Erreur",icon:"fa-times",color:"#f44336"},chevauchement:{header:"Chevauchement",icon:"fa-exclamation-triangle",color:"#f44336"},warning:{header:"Attention",icon:"fa-exclamation-triangle",color:"#ff9800"},info:{header:"Information",icon:"fa-info-circle",color:"#2196F3"}},s=n[t]||n.info;r.textContent=s.header,o.textContent=e,l.innerHTML=`<i class="fas ${s.icon}"></i>`,a.style.setProperty("--accent-color",s.color),a.offsetWidth,a.classList.add("show"),notificationTimeout=setTimeout(()=>{a.classList.remove("show"),a.classList.add("hide")},4e3)}function setupExcelSelection(){let e=document.getElementById("timetable"),t=document.createElement("div");t.className="excel-selection",t.style.display="none",document.body.appendChild(t);let a=!1,r=null,o,l;e.addEventListener("mousedown",function(e){if(!e.ctrlKey){clearExcelSelection();return}let n=e.target.closest("td");if(!n||n.classList.contains("formateur-cell")||n.classList.contains("type-cell"))return;e.preventDefault(),a=!0,r=n,o=e.clientX,l=e.clientY;let s=document.getElementById("excelToolbar");s.classList.add("visible");let i=n.getBoundingClientRect();t.style.left=`${i.left}px`,t.style.top=`${i.top}px`,t.style.width=`${i.width}px`,t.style.height=`${i.height}px`,t.style.display="block",document.querySelectorAll(".excel-selected").forEach(e=>{e.classList.remove("excel-selected")}),n.classList.add("excel-selected")}),document.addEventListener("mousemove",function(e){if(!a||!r)return;let o=document.elementFromPoint(e.clientX,e.clientY)?.closest("td");if(!o||o===r)return;let l=r.getBoundingClientRect(),n=o.getBoundingClientRect(),s=Math.min(l.left,n.left),i=Math.max(l.right,n.right),c=Math.min(l.top,n.top),u=Math.max(l.bottom,n.bottom);t.style.left=`${s}px`,t.style.top=`${c}px`,t.style.width=`${i-s}px`,t.style.height=`${u-c}px`;let d=document.querySelectorAll("tbody td");d.forEach(e=>{if(e.classList.contains("formateur-cell")||e.classList.contains("type-cell"))return;let t=e.getBoundingClientRect(),a=t.left>=s&&t.right<=i&&t.top>=c&&t.bottom<=u;a?e.classList.add("excel-selected"):e.classList.remove("excel-selected")})}),document.addEventListener("mouseup",function(){a=!1}),e.addEventListener("contextmenu",function(e){a&&e.preventDefault()})}function setupExcelToolbarListeners(){document.getElementById("copyBtn").addEventListener("click",copySelectedCells),document.getElementById("pasteBtn").addEventListener("click",pasteSelectedCells),document.getElementById("cutBtn").addEventListener("click",cutSelectedCells),document.getElementById("deleteBtn").addEventListener("click",deleteSelectedCells),document.getElementById("clearSelectionBtn").addEventListener("click",clearExcelSelection),document.addEventListener("keydown",function(e){e.ctrlKey?"c"===e.key?(e.preventDefault(),copySelectedCells()):"v"===e.key?(e.preventDefault(),pasteSelectedCells()):"x"===e.key&&(e.preventDefault(),cutSelectedCells()):"Delete"===e.key&&(e.preventDefault(),deleteSelectedCells())}),document.getElementById("clearSelectionBtn").addEventListener("click",clearExcelSelection)}function clearExcelSelection(){document.querySelectorAll(".excel-selected").forEach(e=>{e.classList.remove("excel-selected")});let e=document.querySelector(".excel-selection");e&&(e.style.display="none");let t=document.getElementById("excelToolbar");t.classList.remove("visible")}function showPasteNotification(e){let t=document.getElementById("pasteNotification");t.textContent=e,t.classList.add("show"),setTimeout(()=>t.classList.remove("show"),2e3)}function copySelectedCells(){let e=document.querySelectorAll(".excel-selected");if(0===e.length){showNotification("Aucune cellule s\xe9lectionn\xe9e","warning");return}clipboard={type:"copy",data:[],matrix:[]};let t={};e.forEach(e=>{let a=e.closest("tr").rowIndex,r=e.cellIndex;t[a]||(t[a]={}),t[a][r]=e});let a=Object.keys(t).sort((e,t)=>e-t);a.forEach(e=>{let a=[],r=Object.keys(t[e]).sort((e,t)=>e-t);r.forEach(r=>{let o=t[e][r],l=o.querySelector("select");l&&a.push({formateur:l.dataset.formateur,jour:l.dataset.jour,seance:l.dataset.seance,type:l.dataset.type,value:l.value})}),clipboard.matrix.push(a)}),showPasteNotification(`${e.length} cellules copi\xe9es`)}function pasteSelectedCells(){if(!clipboard||!clipboard.matrix||0===clipboard.matrix.length){showNotification("Aucune donn\xe9e \xe0 coller","warning");return}let e=document.querySelectorAll(".excel-selected");if(0===e.length){showNotification("Aucune cellule s\xe9lectionn\xe9e pour le collage","warning");return}let t=[],a={};e.forEach(e=>{let t=e.closest("tr").rowIndex,r=e.cellIndex;a[t]||(a[t]={}),a[t][r]=e});let r=Object.keys(a).sort((e,t)=>e-t);r.forEach(e=>{let r=[],o=Object.keys(a[e]).sort((e,t)=>e-t);o.forEach(t=>{r.push(a[e][t])}),t.push(r)});let o=0;for(let l=0;l<t.length;l++)for(let n=0;n<t[l].length;n++){let s=l%clipboard.matrix.length,i=n%clipboard.matrix[0].length,c=clipboard.matrix[s][i],u=t[l][n],d=u.querySelector("select");if(d&&d.dataset.type===c.type){d.value=c.value;let p=new Event("change");d.dispatchEvent(p),updateTimetableData(d.dataset.jour,d.dataset.seance,d.dataset.formateur,d.dataset.type,c.value);let f=d.closest("td");c.value?(f.classList.add("selected-cell"),f.classList.remove("empty-cell")):(f.classList.remove("selected-cell"),f.classList.add("empty-cell")),"groupe"===d.dataset.type&&updateModuleOptions(d.dataset.formateur,d.dataset.jour,d.dataset.seance,c.value),o++}}o>0?showPasteNotification(`${o} cellules coll\xe9es`):showNotification("Aucune cellule compatible pour le collage","warning")}function cutSelectedCells(){copySelectedCells();let e=document.querySelectorAll(".excel-selected");e.forEach(e=>{let t=e.querySelectorAll("select");t.forEach(e=>{e.value="";let t=new Event("change");e.dispatchEvent(t),updateTimetableData(e.dataset.jour,e.dataset.seance,e.dataset.formateur,e.dataset.type,"");let a=e.closest("td");a.classList.remove("selected-cell"),a.classList.add("empty-cell")})}),showPasteNotification("Cellules coup\xe9es")}function deleteSelectedCells(){let e=document.querySelectorAll(".excel-selected");if(0===e.length){showNotification("Aucune cellule s\xe9lectionn\xe9e","warning");return}let t=0;e.forEach(e=>{let a=e.querySelectorAll("select");a.forEach(e=>{e.value="";let a=new Event("change");e.dispatchEvent(a),updateTimetableData(e.dataset.jour,e.dataset.seance,e.dataset.formateur,e.dataset.type,"");let r=e.closest("td");r.classList.remove("selected-cell"),r.classList.add("empty-cell"),t++})}),showPasteNotification(`${t} cellules effac\xe9es`)}function setupEventListeners(){let e=document.getElementById("fileInput"),t=document.getElementById("resetBtn"),a=document.getElementById("autoGenerateBtn"),r=document.getElementById("closeModalBtn"),o=document.getElementById("runAutoGenerateBtn");e&&e.addEventListener("change",handleFileUpload),t&&t.addEventListener("click",resetTimetable),a&&a.addEventListener("click",openAutoGenModal),r&&r.addEventListener("click",()=>{document.getElementById("autoGenModal").style.display="none"}),o&&o.addEventListener("click",runAutoGeneration);let l=document.getElementById("timetable");l&&(l.removeEventListener("change",handleTableInteraction),l.addEventListener("change",handleTableInteraction),l.addEventListener("mousedown",function(e){"SELECT"===e.target.tagName&&("salle"===e.target.dataset.type?updateSalleOptions(e.target):"groupe"===e.target.dataset.type&&styleGroupOptions(e.target))})),document.getElementById("importWeekBtn")?.addEventListener("click",showWeekDropdown),document.addEventListener("click",e=>{let t=document.getElementById("weekImportMenu"),a=document.getElementById("importWeekBtn");!t.classList.contains("show")||t.contains(e.target)||a.contains(e.target)||t.classList.remove("show")});let n=document.getElementById("analyzeHoursBtn");n&&n.addEventListener("click",()=>{if(!timetableData||0===Object.keys(timetableData).length){showNotification("Aucun emploi du temps \xe0 analyser. Veuillez charger ou g\xe9n\xe9rer des donn\xe9es.","warning");return}calculateAndShowGroupHours(timetableData)}),document.getElementById("closeGroupHoursModal").addEventListener("click",()=>{document.getElementById("groupHoursModal").style.display="none"}),window.addEventListener("click",e=>{let t=document.getElementById("groupHoursModal");e.target==t&&(t.style.display="none")})}function loadWeekData(e){let t=document.getElementById("weekLoader");t.style.display="flex",fetch(`../api/data/get_timetable.php?semaine=${e}`).then(e=>e.json()).then(e=>{t.style.display="none",e.success?(renderTimetableFromData(timetableData=e.data),showNotification("Emploi du temps charg\xe9","success")):(initializeTimetableData(),renderTimetableFromData(timetableData),showNotification("Aucun EDT trouv\xe9 pour cette semaine, tableau initialis\xe9.","info"))}).catch(e=>{t.style.display="none",showNotification("Erreur r\xe9seau: "+e.message,"error")})}function isModuleRegional(e,t,a){if(!e||!t||!a)return!1;let r=t.trim().split(/\s+/).filter(Boolean);for(let o of r){let l=appData.affectations.find(t=>t.formateur===e&&t.groupe===o&&t.module===a);if(l&&l.hasOwnProperty("est_regional")){let n=l.est_regional;return!0===n||1==n||"true"===String(n).toLowerCase()}}return!1}function updateModuleOptions(e,t,a,r){let o=document.querySelector(`select[data-formateur="${e}"][data-jour="${t}"][data-seance="${a}"][data-type="module"]`);if(!o)return;let l=o.value;if(o.innerHTML='<option value=""></option>',!r){o.disabled=!0,handleModuleChange({target:o});return}o.disabled=!1;let n=r.trim().split(/\s+/).filter(Boolean),s=new Map;appData.affectations.forEach(t=>{if(t.formateur===e&&n.includes(t.groupe)){let a=t.module,r=getModuleSemester(e,t.groupe,a),o=isModuleRegional(e,t.groupe,a);s.set(a,{semesterStatus:r,estRegional:o})}}),s.forEach((e,t)=>{let a=document.createElement("option");a.value=t;let r=t;e.estRegional&&(r="⭐ "+t),a.textContent=r,a.dataset.semester=e.semesterStatus,a.dataset.regional=e.estRegional?"true":"false",t===l&&(a.selected=!0),o.appendChild(a)}),l&&!s.has(l)&&(o.value="");let i=new Event("change",{bubbles:!0});o.dispatchEvent(i)}function renderTimetableFromData(e){let t=document.querySelector("#timetable tbody"),a="";if(!appData.formateurs||0===appData.formateurs.length){t.innerHTML='<tr><td colspan="26" style="text-align:center; padding: 20px;">Donn\xe9es de base non charg\xe9es. Veuillez importer un fichier Excel.</td></tr>';return}for(let r in initializeTimetableData(),e&&(timetableData=JSON.parse(JSON.stringify(e))),groupHours={},timetableData)for(let o in timetableData[r])for(let l in timetableData[r][o]){let n=timetableData[r][o][l];if(n.groupe){let s=n.groupe.trim().split(/\s+/).filter(e=>e);s.forEach(e=>{groupHours[e]=(groupHours[e]||0)+2.5})}}appData.formateurs.forEach(e=>{let t=timetableData[e]||{},r=0;t&&Object.values(t).forEach(e=>{Object.values(e).forEach(e=>{e.groupe&&r++})});let o=2.5*r,l="formateur-green";o>35?l="formateur-red":o>22.5&&(l="formateur-orange"),a+=`<tr>
          <td rowspan="3" class="formateur-cell ${l}" data-formateur="${e}">
            <div class="formateur-content">
              <div class="formateur-name">${e}</div>
              <div class="formateur-hours" id="hours-${e}">${o.toFixed(1)}</div>
            </div>
          </td>
          <td class="type-cell">Groupe</td>`,jours.forEach(r=>{seances.forEach(o=>{let l=t[r]?.[o]||{groupe:"",module:"",salle:""},n=dayClasses[r],s=l.groupe?"selected-cell":"",i=l.groupe&&groupHours[l.groupe]>=40?"group-highlight":"",c="TEAMS"===l.salle,u=c?appData.fusionGroupes:appData.groupes,d=c?"synchrone":"presentiel",p=u.filter(t=>appData.affectations.some(a=>a.formateur===e&&a.groupe===t&&a.type===d));a+=`<td class="auto-resize-cell draggable-cell ${n} ${s} ${i}" draggable="true" data-type="groupe" data-jour="${r}" data-seance="${o}" data-formateur="${e}">
                  <select class="auto-resize-select" data-type="groupe" data-jour="${r}" data-seance="${o}" data-formateur="${e}">
                    <option value=""></option>
                    ${p.map(e=>`<option value="${e}" ${e===l.groupe?"selected":""}>${e}</option>`).join("")}
                  </select>
                  <div class="drag-indicator"><i class="fas fa-arrows-alt"></i></div>
                </td>`})}),a+="</tr>",a+='<tr><td class="type-cell">Module</td>',jours.forEach(r=>{seances.forEach(o=>{let l=t[r]?.[o]||{groupe:"",module:"",salle:""},n=dayClasses[r],s=l.module?"selected-cell":"",i=getModuleSemester(e,l.groupe,l.module),c=isModuleRegional(e,l.groupe,l.module),u=i?`module-${i.toLowerCase()}`:"",d=new Map;if(l.groupe){let p=l.groupe.trim().split(/\s+/).filter(Boolean);appData.affectations.forEach(t=>{if(t.formateur===e&&p.includes(t.groupe)){let a=getModuleSemester(e,t.groupe,t.module),r=isModuleRegional(e,t.groupe,t.module);a&&d.set(t.module,{semester:a,isRegional:r})}})}let f="";d.forEach((e,t)=>{f+=`<option value="${t}" data-semester="${e.semester}" data-regional="${e.isRegional}" ${t===l.module?"selected":""}>${t}</option>`}),a+=`<td class="auto-resize-cell ${n} ${s} ${u} ${c?"module-regional":""}" data-jour="${r}" data-seance="${o}" data-formateur="${e}">
  <div class="custom-select-wrapper">
    <span class="custom-select-display">${l.module||""}</span>
    <select class="auto-resize-select" data-type="module" data-jour="${r}" data-seance="${o}" data-formateur="${e}" ${l.groupe?"":"disabled"}>
      <option value=""></option>
      ${f}
    </select>
  </div>
</td>`})}),a+="</tr>",a+='<tr class="salle-row"><td class="type-cell">Espace</td>',jours.forEach(r=>{seances.forEach(o=>{let l=t[r]?.[o]||{groupe:"",module:"",salle:""},n=dayClasses[r],s=l.salle?"selected-cell":"";a+=`<td class="auto-resize-cell ${n} ${s}" data-jour="${r}" data-seance="${o}" data-formateur="${e}">
                  <select class="auto-resize-select" data-type="salle" data-jour="${r}" data-seance="${o}" data-formateur="${e}">
                    <option value=""></option>
                    ${appData.espaces.map(e=>`<option value="${e}" ${e===l.salle?"selected":""}>${e}</option>`).join("")}
                  </select>
                </td>`})}),a+="</tr>"}),t.innerHTML=a,document.querySelectorAll(".custom-select-wrapper").forEach(e=>{let t=e.querySelector("select"),a=e.querySelector(".custom-select-display");t&&a&&(a.textContent=t.value,resizeTextToFit(a))}),document.querySelectorAll(".auto-resize-select").forEach(resizeTextToFit),Object.keys(groupHours).forEach(checkGroupHours),setupDragAndDrop()}let dragSrcElement=null;function setupDragAndDrop(){let e=document.querySelectorAll(".draggable-cell");e.forEach(e=>{e.addEventListener("dragstart",handleDragStart,!1),e.addEventListener("dragenter",handleDragEnter,!1),e.addEventListener("dragover",handleDragOver,!1),e.addEventListener("dragleave",handleDragLeave,!1),e.addEventListener("drop",handleDrop,!1),e.addEventListener("dragend",handleDragEnd,!1)})}function handleDragEnter(e){this.dataset.formateur===dragSrcElement.dataset.formateur&&this.classList.add("drop-target")}function handleDragStart(e){dragSrcElement=this;let t=this.dataset.formateur,a=this.dataset.jour,r=this.dataset.seance,o=timetableData[t][a][r];if(!o||!o.groupe){e.preventDefault();return}e.dataTransfer.effectAllowed="move",e.dataTransfer.setData("text/plain",JSON.stringify({formateur:t,jour:a,seance:r})),this.classList.add("dragging");let l=document.querySelectorAll(`td[data-formateur="${t}"][data-type="groupe"]`);l.forEach(e=>{let l=e.dataset.jour,n=e.dataset.seance;if(l===a&&n===r)return;let s=timetableData[t][l][n],i=isSlotAvailableForSession(l,n,o,t),c=isSlotAvailableForSession(a,r,s,t);if(i&&c){let u=document.querySelectorAll(`td[data-formateur="${t}"][data-jour="${l}"][data-seance="${n}"]`);u.forEach(e=>e.classList.add("drop-possible"))}})}function handleDragOver(e){return e.preventDefault&&e.preventDefault(),e.dataTransfer.dropEffect="move",!1}function handleDragEnd(e){document.querySelectorAll(".dragging, .drop-target, .drop-possible").forEach(function(e){e.classList.remove("dragging","drop-target","drop-possible")})}function handleDragLeave(e){this.classList.remove("drop-target")}function handleDrop(e){if(e.stopPropagation(),dragSrcElement===this)return!1;let t=JSON.parse(e.dataTransfer.getData("text/plain")),a=t.formateur,r=t.jour,o=t.seance,l=this.dataset.formateur,n=this.dataset.jour,s=this.dataset.seance;if(a!==l)return showNotification("D\xe9placement non autoris\xe9 entre formateurs.","warning"),!1;let i=JSON.parse(JSON.stringify(timetableData[a][r][o])),c=JSON.parse(JSON.stringify(timetableData[l][n][s])),u=JSON.parse(JSON.stringify(timetableData));u[a][r][o]=c,u[l][n][s]=i;let d=checkSessionConflict(r,o,c,u,{formateur:a});if(d)return showNotification(`D\xe9placement impossible: ${d}`,"chevauchement"),!1;let p=checkSessionConflict(n,s,i,u,{formateur:l});return p?(showNotification(`D\xe9placement impossible: ${p}`,"chevauchement"),!1):(timetableData[a][r][o]=c,timetableData[l][n][s]=i,showNotification("Session d\xe9plac\xe9e avec succ\xe8s.","success"),renderTimetableFromData(timetableData),document.querySelectorAll(".dragging, .drop-target, .drop-possible").forEach(function(e){e.classList.remove("dragging","drop-target","drop-possible")}),!1)}function checkSessionConflict(e,t,a,r,o){if(!a||!a.groupe)return null;let l=a.groupe.trim().split(/\s+/).filter(Boolean),n=a.salle;for(let s in r){if(s===o.formateur)continue;let i=r[s][e][t];if(n&&"TEAMS"!==n&&i.salle===n)return`Conflit de salle pour "${n}" avec le formateur ${s}.`;if(i.groupe){let c=i.groupe.trim().split(/\s+/).filter(Boolean);if(l.some(e=>c.includes(e)))return`Conflit de groupe ("${a.groupe}") avec le formateur ${s}.`}}return null}function handleDragEnd(e){document.querySelectorAll(".draggable-cell").forEach(function(e){e.classList.remove("dragging","drop-target")})}let clipboardConfiguration=null;function openAutoGenModal(){let e=document.getElementById("autoGenConstraintsContainer"),t=[...new Set(appData.groupes.map(getAnneeFormation))].filter(e=>"N/A"!==e).sort(),a="";t.length>0&&(a=`
            <div style="margin-top: 0px;">
                <h4 style="margin-top:0; margin-bottom: 8px; color: #2c3e50; font-weight: 600;">
                    <i class="fas fa-percentage"></i> R\xe9partition par Niveau
                </h4>
                <div id="tauxContainer" style="display: flex; flex-wrap: wrap; gap: 15px; align-items: center; padding: 8px; border-radius: 8px;">
                    ${t.map(e=>`
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <label for="taux-${e}" style="font-weight: 500; color: #34495e; min-width: auto;">
                                ${e} :
                            </label>
                            <div style="position: relative;">
                                <input type="number" id="taux-${e}" class="taux-input" min="0" max="100" 
                                       value="${"1A"===e?100:0}" data-annee="${e}" 
                                       style="width: 80px; height: 30px; padding: 9px 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 1rem; background: #ffffff; transition: all 0.3s;">
                                <span style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); font-weight: bold; color: #7f8c8d; pointer-events: none;">%</span>
                            </div>
                        </div>
                    `).join("")}
                    <div id="tauxTotal" style="margin-left: auto; text-align: right; font-weight: 700; font-size: 1.1rem; transition: color 0.3s; color: #27ae60;">
                      Total : <span id="tauxTotalValue">100</span>%
                    </div>
                </div>
            </div>
        `);let r=`
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
                            <i class="fas fa-map-marker-alt"></i> G\xe9rer les Espaces
                        </label>
                        <div style="display: flex; gap: 8px;">
                            <select id="bulkSpaceSelect" style="flex: 1; height: 35px; padding: 7px 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 1rem; background: #ffffff;">
                                ${appData.espaces.map(e=>`<option value="${e}">${e}</option>`).join("")}
                            </select>
                            <button data-action="add-space" class="btn-success" style="padding: 10px 16px; height: 35px;">
                                <i class="fas fa-plus"></i>
                            </button>
                            <button data-action="remove-space" class="btn-danger" style="padding: 10px 16px; height: 35px;">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>

                    ${a}

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

                <!-- Colonne de droite: Grille de s\xe9lection des indisponibilit\xe9s -->
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">
                        <i class="fas fa-calendar-times"></i> G\xe9rer les Indisponibilit\xe9s
                    </label>
                    <div style="color: #7f8c8d; font-size: 0.9rem; margin-bottom: 10px;">
                        Cliquez-glissez pour s\xe9lectionner
                    </div>
                    <div style="display: flex; gap: 10px; margin-top: 5px;">
                        <!-- === GRILLE DE S\xc9LECTION INVERS\xc9E === -->
                        <table id="bulkAvailabilityGrid" class="availability-grid-style">
                            <thead>
                                <tr>
                                    <th></th> <!-- Coin vide -->
                                    ${seances.map(e=>`<th style="padding: 8px 10px; background: #f8fafc;">${e}</th>`).join("")}
                                </tr>
                            </thead>
                            <tbody>
                                ${jours.map(e=>`
                                    <tr>
                                        <th style="padding: 8px 10px; background: #f8fafc;">${e}</th>
                                        ${seances.map(t=>`<td class="bulk-slot" data-jour="${e}" data-seance="${t}"></td>`).join("")}
                                    </tr>
                                `).join("")}
                            </tbody>
                        </table>
                        <div style="display: flex; flex-direction: column; gap: 8px; justify-content: center;">
                            <button data-action="block-availability" class="btn-danger" style="padding: 10px 16px;">
                                <i class="fas fa-ban"></i> Bloquer
                            </button>
                            <button data-action="unblock-availability" class="btn-success" style="padding: 10px 16px;">
                                <i class="fas fa-check-circle"></i> D\xe9bloquer
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px; padding-top: 10px; border-top: 1px solid #e2e8f0;">
            <div id="bulkSelectionInfo" style="font-weight: 500; color: #333; font-size: 1.05rem;">Aucun formateur s\xe9lectionn\xe9.</div>
            <button id="invertSelectionBtn" class="btn-secondary" style="padding: 8px 16px; width: 177px !important;">
              <i class="fas fa-exchange-alt"></i> Inverser la s\xe9lection
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

            /* Tableau de s\xe9lection en masse */
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

            /* Styles am\xe9lior\xe9s pour les inputs */
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
            
            /* Style pour les en-t\xeates */
            h4 {
                color: #2c3e50;
                font-weight: 600;
                margin-bottom: 10px;
            }
        </style>
    `,o=`
        <table class="constraints-table">
            <thead>
                <tr>
                    <th style="width: 20px;"><input type="checkbox" id="selectAllFormateurs"></th>
                    <th>Formateur</th>
                    <th>Heures/Sem.</th>
                    <th>Sessions TEAMS</th>
                    <th>Espaces Autoris\xe9s</th>
                    <th style="width: 220px;">Indisponibilit\xe9s</th>
                </tr>
            </thead>
            <tbody>`;appData.formateurs.forEach(e=>{o+=`<tr data-formateur="${e}">
            <td><input type="checkbox" class="formateur-select-checkbox"></td>
            <td class="formateur-name-col">${e}</td>
            <td><input type="number" class="formateur-hours-autogen" value="25" step="2.5"></td>
            <td><input type="number" class="formateur-teams-sessions" value="0"></td>
            <td><div class="tags-container assigned-spaces-container"></div></td>
            
            <td>
                <table class="availability-grid-style availability-mini-grid">
                    
                    <tbody>
                        ${jours.map(e=>`
                            <tr>
                                <th>${e.substring(0,3)}</th>
                                ${seances.map(t=>`<td class="mini-slot" data-jour="${e}" data-seance="${t}" title="${e} ${t}"></td>`).join("")}
                            </tr>
                        `).join("")}
                    </tbody>
                </table>
            </td>
        </tr>`}),o+="</tbody></table>";let l=e.parentElement,n=l.querySelector("#bulkEditPanel");n&&n.remove(),e.insertAdjacentHTML("beforebegin",r),e.innerHTML=o,document.getElementById("autoGenModal").style.display="block";try{let s=localStorage.getItem("autoGenConfig_v1");if(s){let i=JSON.parse(s);document.querySelectorAll("#autoGenConstraintsContainer tr[data-formateur]").forEach(e=>{let t=e.dataset.formateur;if(i[t]){let a=i[t];e.querySelector(".formateur-hours-autogen").value=a.hours||25,e.querySelector(".formateur-teams-sessions").value=a.teamsSessions||0;let r=e.querySelector(".assigned-spaces-container");r.innerHTML="",a.spaces&&Array.isArray(a.spaces)&&a.spaces.forEach(t=>addSpaceTag(e,t)),a.unavailable&&Array.isArray(a.unavailable)&&a.unavailable.forEach(t=>{let a=e.querySelector(`.mini-slot[data-jour="${t.jour}"][data-seance="${t.seance}"]`);a&&a.classList.add("unavailable")})}}),showNotification("Derni\xe8re configuration charg\xe9e.","info")}}catch(c){console.error("Erreur lors du chargement de la configuration sauvegard\xe9e:",c),localStorage.removeItem("autoGenConfig_v1")}setupBulkEventListeners()}function updateSelectionInfo(){let e=document.getElementById("bulkSelectionInfo"),t=document.querySelectorAll("#autoGenConstraintsContainer tr.selected-row"),a=t.length;if(0===a)e.textContent="Aucun formateur s\xe9lectionn\xe9.";else{let r=Array.from(t).map(e=>e.dataset.formateur),o=`<strong>${a} formateur(s) s\xe9lectionn\xe9(s) :</strong> `;r.length>5?o+=r.slice(0,5).join("- ")+` et ${r.length-5} autre(s)...`:o+=r.join("- "),e.innerHTML=o}}function getAnneeFormation(e){if(!e||"string"!=typeof e||e.length<3)return"N/A";let t=parseInt(e.slice(-3),10);return isNaN(t)?"N/A":t>=100&&t<200?"1A":t>=200&&t<300?"2A":t>=300&&t<400?"3A":"Autre"}function updateTauxTotal(){let e=document.querySelectorAll(".taux-input"),t=0;e.forEach(e=>{t+=parseFloat(e.value)||0});let a=document.getElementById("tauxTotalValue"),r=document.getElementById("tauxTotal");a.textContent=t.toFixed(0),"100"!==t.toFixed(0)?r.classList.add("taux-total-warning"):r.classList.remove("taux-total-warning")}function saveAutoGenConfig(){let e={};document.querySelectorAll("#autoGenConstraintsContainer tr[data-formateur]").forEach(t=>{let a=t.dataset.formateur;e[a]={hours:parseFloat(t.querySelector(".formateur-hours-autogen").value)||25,teamsSessions:parseInt(t.querySelector(".formateur-teams-sessions").value,10)||0,spaces:Array.from(t.querySelectorAll(".space-tag")).map(e=>e.dataset.space),unavailable:Array.from(t.querySelectorAll(".mini-slot.unavailable")).map(e=>({jour:e.dataset.jour,seance:e.dataset.seance}))}}),localStorage.setItem("autoGenConfig_v1",JSON.stringify(e)),console.log("Configuration de la g\xe9n\xe9ration auto sauvegard\xe9e.")}function setupBulkEventListeners(){let e=document.getElementById("autoGenModal");if(!e)return;let t=()=>[...e.querySelectorAll(".formateur-select-checkbox:checked")].map(e=>e.closest("tr")),a=()=>{let a=t(),r=a.length,o=e.querySelectorAll(".formateur-select-checkbox").length,l=document.getElementById("bulkSelectionInfo"),n=document.getElementById("selectAllFormateurs"),s=`(${r} / ${o})`;if(0===r)l.textContent=`Aucun formateur s\xe9lectionn\xe9 ${s}`;else{let i=a.map(e=>e.querySelector(".formateur-name-col").textContent.trim());l.textContent=`S\xe9lection : ${i.join(", ")} ${s}`}n.checked=r>0&&r===o,n.indeterminate=r>0&&r<o,e.querySelectorAll("tr[data-formateur]").forEach(e=>{let t=e.querySelector(".formateur-select-checkbox");e.classList.toggle("selected-row",t&&t.checked)})};e.addEventListener("click",a=>{let r=a.target,o=r.closest("[data-action]");if(r.matches(".mini-slot")){r.classList.toggle("unavailable"),saveAutoGenConfig();return}if(!o)return;let l=o.dataset.action,n=t();if(0===n.length&&!["block-availability","unblock-availability"].includes(l)){if("add-space"===l||"remove-space"===l);else{showNotification("Veuillez s\xe9lectionner au moins un formateur.","warning");return}}switch(l){case"apply-hours":let s=document.getElementById("bulkHours").value;n.forEach(e=>e.querySelector(".formateur-hours-autogen").value=s);break;case"apply-teams":let i=document.getElementById("bulkTeams").value;n.forEach(e=>e.querySelector(".formateur-teams-sessions").value=i);break;case"add-space":let c=document.getElementById("bulkSpaceSelect").value;n.forEach(e=>addSpaceTag(e,c));break;case"remove-space":let u=document.getElementById("bulkSpaceSelect").value;n.forEach(e=>{let t=e.querySelector(`.space-tag[data-space="${u}"]`);t&&t.remove()});break;case"remove-specific-space":r.closest(".space-tag").remove();break;case"block-availability":case"unblock-availability":let d="block-availability"===l,p=e.querySelectorAll("#bulkAvailabilityGrid td.selected");if(0===p.length){showNotification("Veuillez s\xe9lectionner des cr\xe9neaux dans la grille principale.","warning");return}if(0===n.length){showNotification("Veuillez s\xe9lectionner au moins un formateur.","warning");return}n.forEach(e=>{p.forEach(t=>{let{jour:a,seance:r}=t.dataset,o=e.querySelector(`.mini-slot[data-jour="${a}"][data-seance="${r}"]`);o&&o.classList.toggle("unavailable",d)})}),p.forEach(e=>e.classList.remove("selected"))}saveAutoGenConfig()}),e.addEventListener("change",t=>{if(t.target.matches(".formateur-select-checkbox, #selectAllFormateurs")){let r=document.getElementById("selectAllFormateurs").checked;"selectAllFormateurs"===t.target.id&&e.querySelectorAll(".formateur-select-checkbox").forEach(e=>e.checked=r),a()}else t.target.matches(".formateur-hours-autogen, .formateur-teams-sessions")&&saveAutoGenConfig()}),document.getElementById("invertSelectionBtn")?.addEventListener("click",()=>{e.querySelectorAll(".formateur-select-checkbox").forEach(e=>e.checked=!e.checked),a()});let r=document.getElementById("tauxContainer");r&&r.addEventListener("input",e=>{e.target.classList.contains("taux-input")&&updateTauxTotal()});let o=document.getElementById("bulkAvailabilityGrid");if(o){let l=!1;o.addEventListener("mousedown",e=>{"TD"===e.target.tagName&&(l=!0,e.target.classList.toggle("selected"),e.preventDefault())}),o.addEventListener("mouseover",e=>{l&&"TD"===e.target.tagName&&e.target.classList.add("selected")}),document.addEventListener("mouseup",()=>l=!1)}a(),r&&updateTauxTotal()}function addSpaceTag(e,t){let a=e.querySelector(".assigned-spaces-container");if(!a||a.querySelector(`.space-tag[data-space="${t}"]`))return;let r=document.createElement("span");r.className="space-tag",r.dataset.space=t,r.innerHTML=`${t}<button class="remove-tag" data-action="remove-specific-space" title="Retirer">\xd7</button>`,a.appendChild(r)}function findBestSlotForSingle(e,t,a,r){let o=null,l=-1,n=e.formateur,s=t[n];for(let i of jours)for(let c of seances){let u={jour:i,seance:c};if(!isSlotValidForSingle(e,u,a,r))continue;let d=1,p=seances.indexOf(c);p>0&&s[i][seances[p-1]].groupe&&(d+=50),p<seances.length-1&&s[i][seances[p+1]].groupe&&(d+=50),seances.some(e=>s[i][e].groupe)&&(d+=10),d>l&&(l=d,o=u)}return o}async function runAutoGeneration(){document.getElementById("weekLoader").style.display="flex",await new Promise(e=>setTimeout(e,50)),saveAutoGenConfig(),autoGenConstraints={},document.querySelectorAll("#autoGenConstraintsContainer tr[data-formateur]").forEach(e=>{let t=e.dataset.formateur;autoGenConstraints[t]={hours:parseFloat(e.querySelector(".formateur-hours-autogen").value)||0,teamsSessions:parseInt(e.querySelector(".formateur-teams-sessions").value,10)||0,spaces:Array.from(e.querySelectorAll(".space-tag")).map(e=>e.dataset.space),unavailable:Array.from(e.querySelectorAll(".mini-slot.unavailable")).map(e=>({jour:e.dataset.jour,seance:e.dataset.seance}))}});let e={},t={formateurHours:{},formateurTeamsCount:{},roomSlots:{},groupSlots:{},formateurSlots:{},groupHours:{}};appData.formateurs.forEach(a=>{e[a]={},t.formateurHours[a]=0,t.formateurTeamsCount[a]=0,jours.forEach(t=>{e[a][t]={},seances.forEach(r=>e[a][t][r]={groupe:"",module:"",salle:""})})});let a=new Set;document.getElementById("forceS1").checked&&a.add("S1"),document.getElementById("forceS2").checked&&a.add("S2"),document.getElementById("forceAnnual").checked&&a.add("Annual");let r=appData.affectations.filter(e=>a.has(getModuleSemester(e.formateur,e.groupe,e.module))),o=createCourseRequirements(r),l=prioritizeTasks(o,autoGenConstraints),n=[];console.log("Phase unique : Placement forc\xe9 de toutes les sessions en blocs de 5h..."),l.forEach(a=>{let r=Math.ceil(a.sessionsNeeded/2);for(let o=0;o<r;o++){let l=findBestSlotsForBlock(a,e,t,autoGenConstraints);if(l.length>0){let s=l[Math.floor(Math.random()*l.length)];tryPlaceBlock(a,s,e,t),a.sessionsNeeded-=2}else{n.push({task:a,reason:`Bloc 5h forc\xe9 (${a.groupe}) : N'a pas pu \xeatre plac\xe9 (tour ${o+1}/${r}).`});break}}for(a.sessionsNeeded>0&&n.push({task:a,reason:`Sessions restantes non plac\xe9es (${a.groupe}) : Impossible de respecter la r\xe8gle des blocs de 5h.`});a.sessionsNeeded>0;){let i=findBestSlotsForSingle(a,e,t,autoGenConstraints);if(i.length>0){let c=i[Math.floor(Math.random()*i.length)];tryPlaceSingle(a,c,e,t),a.sessionsNeeded-=1}else{n.push({task:a,reason:`Session individuelle non plac\xe9e (${a.groupe}) : aucune option disponible.`});break}}}),renderTimetableFromData(e),document.getElementById("autoGenModal").style.display="none",document.getElementById("weekLoader").style.display="none",calculateAndShowGroupHours(e),console.log("T\xe2ches non plac\xe9es :",n);let s=n.length;s>0?showNotification(`${s} t\xe2che(s) n'a/n'ont pas pu \xeatre plac\xe9e(s). Consultez la console.`,"warning"):showNotification("G\xe9n\xe9ration al\xe9atoire termin\xe9e avec succ\xe8s !","success")}function createCourseRequirements(e){let t=new Map;return e.forEach(e=>{let a=`${e.formateur}|${e.groupe}|${e.module}|${e.type}`;t.has(a)||t.set(a,{...e,sessionsNeeded:0}),t.get(a).sessionsNeeded++}),Array.from(t.values())}function getDifficultyScore(e,t){let a=t[e.formateur];if(!a)return 0;let r=jours.length*seances.length-(a.unavailable?.length||0);return a.hours/(r||1)}function prioritizeTasks(e,t){return shuffleArray(e),e.sort((e,a)=>getDifficultyScore(a,t)-getDifficultyScore(e,t))}function findBestSlotsForBlock(e,t,a,r){let o=[],l=-1/0,n=e.formateur,s=r[n];if(!s)return[];let i=[["S1","S2"],["S3","S4"]],c=[...jours];shuffleArray(c);let u=a.groupHours[e.groupe]||0,d=(40-u)*10;for(let p of c)for(let f of i){let[m,g]=f;if(s.unavailable.some(e=>e.jour===p&&(e.seance===m||e.seance===g))||a.formateurSlots[`${p}-${m}-${n}`]||a.formateurSlots[`${p}-${g}-${n}`]||isGroupBusy(p,m,e.groupe,a)||isGroupBusy(p,g,e.groupe,a)||a.formateurHours[n]+5>s.hours)continue;let h=[...s.spaces];for(let b of(shuffleArray(h),h)){let y=!1;if("synchrone"===e.type?"TEAMS"===b&&a.formateurTeamsCount[n]<s.teamsSessions&&(y=!0):"TEAMS"===b||a.roomSlots[`${p}-${m}-${b}`]||a.roomSlots[`${p}-${g}-${b}`]||(y=!0),y){let $=100+d;Object.values(t[n][p]).some(e=>e.groupe)&&($+=10);let v={jour:p,pair:f,salle:b};$>l?(l=$,o=[v]):$===l&&o.push(v)}}}return o}function findBestSlotsForSingle(e,t,a,r){let o=[],l=-1/0,n=e.formateur,s=r[n];if(!s)return[];let i=[...jours];shuffleArray(i);let c=a.groupHours[e.groupe]||0,u=(40-c)*10;for(let d of i)for(let p of seances){if(a.formateurSlots[`${d}-${p}-${n}`]||isGroupBusy(d,p,e.groupe,a)||s.unavailable.some(e=>e.jour===d&&e.seance===p)||a.formateurHours[n]+2.5>s.hours)continue;let f=[...s.spaces];for(let m of(shuffleArray(f),f)){let g=!1;if("synchrone"===e.type?"TEAMS"===m&&a.formateurTeamsCount[n]<s.teamsSessions&&(g=!0):"TEAMS"===m||a.roomSlots[`${d}-${p}-${m}`]||(g=!0),g){let h=1+u,b=seances.indexOf(p);b>0&&t[n][d][seances[b-1]].groupe&&(h+=50),b<seances.length-1&&t[n][d][seances[b+1]].groupe&&(h+=50),Object.values(t[n][d]).some(e=>e.groupe)&&(h+=10);let y={jour:d,seance:p,salle:m};h>l?(l=h,o=[y]):h===l&&o.push(y)}}}return o}function tryPlaceBlock(e,t,a,r){let{jour:o,pair:l,salle:n}=t,[s,i]=l,c=e.formateur,u={groupe:e.groupe,module:e.module,salle:n};a[c][o][s]=u,a[c][o][i]=u,r.formateurHours[c]+=5,r.formateurSlots[`${o}-${s}-${c}`]=!0,r.formateurSlots[`${o}-${i}-${c}`]=!0;let d=e.groupe.trim().split(/\s+/).filter(Boolean);d.forEach(e=>{r.groupSlots[`${o}-${s}-${e}`]=!0,r.groupSlots[`${o}-${i}-${e}`]=!0,r.groupHours[e]=(r.groupHours[e]||0)+5}),"TEAMS"===n?r.formateurTeamsCount[c]++:(r.roomSlots[`${o}-${s}-${n}`]=!0,r.roomSlots[`${o}-${i}-${n}`]=!0)}function tryPlaceSingle(e,t,a,r){let{jour:o,seance:l,salle:n}=t,s=e.formateur;a[s][o][l]={groupe:e.groupe,module:e.module,salle:n},r.formateurHours[s]+=2.5,r.formateurSlots[`${o}-${l}-${s}`]=!0;let i=e.groupe.trim().split(/\s+/).filter(Boolean);i.forEach(e=>{r.groupSlots[`${o}-${l}-${e}`]=!0,r.groupHours[e]=(r.groupHours[e]||0)+2.5}),"TEAMS"===n?r.formateurTeamsCount[s]++:r.roomSlots[`${o}-${l}-${n}`]=!0}function isGroupBusy(e,t,a,r){let o=a.trim().split(/\s+/).filter(Boolean);return o.some(a=>r.groupSlots[`${e}-${t}-${a}`])}function shuffleArray(e){for(let t=e.length-1;t>0;t--){let a=Math.floor(Math.random()*(t+1));[e[t],e[a]]=[e[a],e[t]]}}function setupVoiceAssistant(){if(!SpeechRecognition){voiceAssistantBtn.style.display="none",console.warn("L'API Web Speech n'est pas support\xe9e par ce navigateur.");return}voiceAssistantBtn.addEventListener("click",toggleListening),recognition.onresult=e=>{let t=e.results[e.results.length-1][0].transcript.trim();updateStatus(`J'ai entendu : "${t}"`),processCommand(t)},recognition.onerror=e=>{console.error("Erreur de reconnaissance vocale:",e.error),updateStatus("D\xe9sol\xe9, je n'ai pas compris.",3e3,!0),stopListening()},recognition.onend=()=>{stopListening()}}function toggleListening(){voiceAssistantBtn.classList.contains("listening")?recognition.stop():startListening()}function startListening(){voiceAssistantBtn.classList.add("listening"),voiceAssistantBtn.querySelector("span i").className="fas fa-wave-square",updateStatus("Je vous \xe9coute...",0),recognition.start()}function stopListening(){voiceAssistantBtn.classList.remove("listening"),voiceAssistantBtn.querySelector("span i").className="fas fa-microphone",setTimeout(()=>assistantStatus.classList.remove("visible"),500)}function updateStatus(e,t=2e3,a=!1){assistantStatus.textContent=e,assistantStatus.style.backgroundColor=a?"#e74c3c":"rgba(0, 0, 0, 0.75)",assistantStatus.classList.add("visible"),t>0&&setTimeout(()=>{assistantStatus.classList.remove("visible")},t)}function speak(e){let t=new SpeechSynthesisUtterance(e);t.lang="fr-FR",t.rate=1.1,speechSynthesis.cancel(),speechSynthesis.speak(t)}let conversationContext=null;async function processCommand(e){let t="AIzaSyCtvJkZ0xp7eSEIVVThjiYQ9HsK84ca_bU";if(t.includes("VOTRE_CLE")){let a="Erreur : La cl\xe9 API Gemini n'a pas \xe9t\xe9 configur\xe9e dans le code.";speak(a),updateStatus(a,4e3,!0),console.error(a);return}let r=`https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=${t}`;if(updateStatus("R\xe9flexion en cours...",0),conversationContext&&"confirm_generation"===conversationContext.action){if(e.toLowerCase().match(/oui|lance|vas-y|confirme|ok|d'accord/)){speak("Parfait, je lance la g\xe9n\xe9ration imm\xe9diatement."),openAutoGenModal(),setTimeout(()=>document.getElementById("runAutoGenerateBtn").click(),500),conversationContext=null;return}speak("D'accord, la g\xe9n\xe9ration automatique est annul\xe9e."),conversationContext=null;return}let o=appData.formateurs.map(e=>e.toUpperCase()),l=o.length>0?`Voici la liste des formateurs valides que tu dois utiliser : ${o.join(", ")}.`:"Aucun formateur n'est charg\xe9 pour le moment.",n=`
            Tu es un assistant IA pour une application de gestion d'emplois du temps. ${l}
            Ton r\xf4le est de convertir la requ\xeate de l'utilisateur en un objet JSON structur\xe9 et de fournir une r\xe9ponse textuelle pour initier un dialogue si n\xe9cessaire.
            Intents possibles : 'afficher_edt', 'enregistrer_edt', 'generer_edt', 'definir_contrainte', 'naviguer_page', 'effacer_edt', 'inconnue'.
            Le format de ta r\xe9ponse DOIT \xeatre UNIQUEMENT un objet JSON.
            Format JSON :
            {
              "intent": "nom_de_l_intention",
              "parameters": {
                "semaine": null,
                "page": null, // 'accueil', 'edition', 'avancement', 'espaces'
                "formateur": null, // DOIT \xeatre un nom de la liste fournie.
                "type_contrainte": null, // 'heures' ou 'indisponibilite'
                "valeur": null
              },
              "response": "phrase de r\xe9ponse naturelle pour l'utilisateur."
            }
            Exemples :
            - Utilisateur: "Va sur la page d'avancement" -> JSON: { "intent": "naviguer_page", "parameters": {"page": "avancement"}, "response": "J'ouvre la page d'avancement." }
            - Utilisateur: "Lance la g\xe9n\xe9ration automatique" -> JSON: { "intent": "generer_edt", "parameters": {}, "response": "Je peux lancer la g\xe9n\xe9ration. Dois-je vous confirmer les contraintes actuelles avant ?" }
            - Utilisateur: "Pour MARTIN, mets 30 heures" -> JSON: { "intent": "definir_contrainte", "parameters": {"formateur": "MARTIN", "type_contrainte": "heures", "valeur": 30}, "response": "Contrainte mise \xe0 jour pour Martin : 30 heures." }
            Maintenant, analyse la requ\xeate suivante : "${e}"
        `;try{let s=await fetch(r,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({contents:[{parts:[{text:n}]}]})});if(!s.ok)throw Error("R\xe9ponse de l'API Google non valide.");let i=await s.json(),c=i.candidates[0].content.parts[0].text,u=c.replace(/```json/g,"").replace(/```/g,"").trim(),d=JSON.parse(u);switch(speak(d.response),d.intent){case"afficher_edt":let p=d.parameters.semaine;if(p){let f=getSchoolYear(new Date),m=getWeekInfo(new Date(f,8,1)).startOfWeek,g=new Date(m);g.setDate(m.getDate()+7*p),updateWeekSelection(g)}break;case"enregistrer_edt":saveTimetable();break;case"effacer_edt":resetTimetable();break;case"generer_edt":handleGenerationCommand();break;case"definir_contrainte":handleConstraintCommand(d.parameters);break;case"naviguer_page":let h=d.parameters.page,b={accueil:"emploi.html",edition:"edition.html",avancement:"avancement.html",espaces:"espaces.html"};h&&b[h]&&(window.location.href=b[h]);break;default:console.log("Intent non reconnu par l'IA.")}}catch(y){console.error("Erreur de communication avec l'API Gemini:",y);let $="D\xe9sol\xe9, une erreur est survenue lors de la communication avec mon intelligence.";speak($),updateStatus($,4e3,!0)}}function handleGenerationCommand(){openAutoGenModal(),setTimeout(()=>{let e="Je suis pr\xeat \xe0 lancer la g\xe9n\xe9ration. ",t=document.querySelectorAll("#autoGenConstraintsContainer tr[data-formateur]");if(t.length>0){let a=t[0],r=a.dataset.formateur,o=a.querySelector(".formateur-hours-autogen").value;e+=`\xc0 titre d'exemple, pour ${r}, le maximum est de ${o} heures. Dois-je lancer la g\xe9n\xe9ration avec les r\xe9glages actuels ?`}else e+="Dois-je continuer ?";speak(e),conversationContext={action:"confirm_generation"}},500)}function handleConstraintCommand(e){if(!e.formateur){speak("Veuillez sp\xe9cifier un nom de formateur valide.");return}openAutoGenModal(),setTimeout(()=>{let t=document.querySelector(`#autoGenConstraintsContainer tr[data-formateur="${e.formateur.toUpperCase()}"]`);if(!t){speak(`D\xe9sol\xe9, je ne trouve pas le formateur nomm\xe9 ${e.formateur}.`);return}if(!e.type_contrainte){t.scrollIntoView({behavior:"smooth",block:"center"}),t.style.backgroundColor="#fff9c4";return}switch(e.type_contrainte){case"heures":let a=t.querySelector(".formateur-hours-autogen");a&&(a.value=e.valeur,a.style.backgroundColor="#a7ffeb",setTimeout(()=>a.style.backgroundColor="",2e3));break;case"indisponibilite":let[r,o]=e.valeur.split(/\s+/),l=t.querySelector(`input[data-jour*="${r}"][data-seance="${o}"]`);l&&(l.checked=!0,l.parentElement.style.backgroundColor="#ffcdd2",setTimeout(()=>l.parentElement.style.backgroundColor="",2e3))}},500)}function getOccupiedRooms(e,t,a){let r=new Set,o=document.querySelectorAll(`select[data-jour="${e}"][data-seance="${t}"][data-type="salle"]`);return o.forEach(e=>{e!==a&&e.value&&"TEAMS"!==e.value&&r.add(e.value)}),r}function updateSalleOptions(e){let t=e.dataset.jour,a=e.dataset.seance,r=e.value,o=getOccupiedRooms(t,a,e);e.innerHTML="";let l=document.createElement("option");l.value="",e.appendChild(l),appData.espaces.forEach(t=>{if(!o.has(t)||t===r){let a=document.createElement("option");a.value=t,a.textContent=t,e.appendChild(a)}}),e.value=r}function getOccupiedGroups(e,t,a){let r=new Set,o=document.querySelectorAll(`select[data-jour="${e}"][data-seance="${t}"][data-type="groupe"]`);return o.forEach(e=>{if(e!==a&&e.value){let t=e.value.trim().split(/\s+/);t.forEach(e=>{e&&r.add(e)})}}),r}function styleGroupOptions(e){let t=e.dataset.jour,a=e.dataset.seance,r=getOccupiedGroups(t,a,e);for(let o of e.options){if(!o.value)continue;let l=o.value.trim().split(/\s+/),n=l.some(e=>r.has(e));n?(o.style.color="red",o.style.fontWeight="bold",o.textContent=`${o.value} (Pris)`):(o.style.color="",o.style.fontWeight="",o.textContent=o.value)}}function formatFrenchDate(e){return e.toLocaleDateString("fr-FR",{day:"2-digit",month:"short"})}async function getSavedWeeks(){try{let e=await fetch("../api/data/get_all_timetables.php");if(!e.ok)throw Error(`Erreur HTTP: ${e.status}`);let t=await e.json(),a=t.map(e=>{let t=parseWeekValue(e.semaine);return t?{value:e.semaine,label:`S${t.weekNumber}`,dates:`${formatFrenchDate(t.startOfWeek)} - ${formatFrenchDate(t.endOfWeek)}`}:null}).filter(Boolean);return a.sort((e,t)=>t.value.localeCompare(e.value)),a}catch(r){return console.error("Erreur lors de la r\xe9cup\xe9ration des semaines:",r),[]}}function parseWeekValue(e){let[t,a]=e.split("-W");if(!t||!a)return null;let r=parseInt(t),o=parseInt(a),l=new Date(r,8,1),n=l.getDay(),s=new Date(l);1!==n&&s.setDate(l.getDate()+(0===n?1:8-n));let i=new Date(s);i.setDate(s.getDate()+(o-1)*7),i.setHours(0,0,0,0);let c=new Date(i);return c.setDate(i.getDate()+6),{schoolYear:r,weekNumber:o,startOfWeek:i,endOfWeek:c}}async function showWeekDropdown(){let e=document.getElementById("weekImportMenu");e.innerHTML='<div style="padding:10px; text-align:center;">Chargement...</div>',e.classList.add("show");let t=await getSavedWeeks();if(0===t.length){e.innerHTML='<div style="padding:10px; text-align:center; color:#777;">Aucune semaine sauvegard\xe9e.</div>';return}e.innerHTML="";let a=t.filter(e=>e.value!==currentWeekValue);if(0===a.length){e.innerHTML='<div style="padding:10px; text-align:center; color:#777;">Aucune autre semaine \xe0 importer.</div>';return}a.forEach(t=>{let a=document.createElement("div");a.className="week-import-item";let r=t.value.split("-W")[0];a.innerHTML=`
            <div>
                <span class="week-import-label">${t.label} (${r})</span>
                <span class="week-import-dates" style="display:block; font-size:0.8em; color:#666;">${t.dates}</span>
            </div>
        `,a.dataset.week=t.value,a.onclick=()=>importWeekData(t.value),e.appendChild(a)})}async function importWeekData(e){if(e===currentWeekValue){showNotification("Vous ne pouvez pas importer la semaine active sur elle-m\xeame.","warning");return}let t=parseWeekValue(e),a=`S${t.weekNumber}`;if(!confirm(`Voulez-vous \xe9craser l'emploi du temps actuel avec celui de la semaine ${a} ?`))return;let r=document.getElementById("weekLoader");r.style.display="flex";try{let o=await fetch(`../api/data/get_timetable.php?semaine=${e}`),l=await o.json();if(!l.success)throw Error(l.message||"Impossible de charger la semaine source.");let n=l.data;timetableData=n,await saveTimetable(),renderTimetableFromData(timetableData),showNotification(`EDT de ${a} import\xe9 et enregistr\xe9 pour la semaine actuelle !`,"success")}catch(s){console.error("Erreur lors de l'importation:",s),showNotification(`Erreur d'importation: ${s.message}`,"error")}finally{r.style.display="none",document.getElementById("weekImportMenu").classList.remove("show")}}async function checkSessionAndLoad(){try{let e=await fetch("../api/auth/verify_session.php"),t=await e.json();if(!t.success){window.location.href="login.html";return}populateUserInfo(t.userData),setupProfileMenu(),setupEventListeners(),setupCustomCalendar(),loadInitialData()}catch(a){console.error("Session check failed:",a),window.location.href="login.html"}}function loadInitialData(){fetch("../api/data/get_base_data.php").then(e=>e.json()).then(e=>{console.log("Donn\xe9es de base re\xe7ues:",e),appData={formateurs:[],groupes:[],fusionGroupes:[],espaces:[],affectations:[]},e.success&&e.data?(appData=e.data).formateurs&&0!==appData.formateurs.length||showNotification("Fichier trait\xe9, mais aucun formateur trouv\xe9. V\xe9rifiez le contenu.","warning"):showNotification(e.message||"Aucune donn\xe9e de base. Veuillez charger un fichier Excel.","warning"),updateWeekSelection(new Date),generateTimetable()}).catch(e=>{console.error("Erreur critique chargement donn\xe9es:",e),showNotification("Erreur de communication avec le serveur.","error"),appData={formateurs:[],groupes:[],fusionGroupes:[],espaces:[],affectations:[]},generateTimetable()})}function getInitials(e){if(!e)return"--";let t=e.trim().split(" ");return 1===t.length?t[0].substring(0,2).toUpperCase():(t[0][0]+t[t.length-1][0]).toUpperCase()}function populateUserInfo(e){e&&(document.getElementById("profile-initials").textContent=getInitials(e.nom),document.getElementById("dropdown-user-name").textContent=e.nom,document.getElementById("dropdown-user-email").textContent=e.email)}function setupProfileMenu(){let e=document.getElementById("profile-button"),t=document.getElementById("profile-dropdown");e&&t&&(e.addEventListener("click",e=>{e.stopPropagation(),t.classList.toggle("show")}),window.addEventListener("click",e=>{t.classList.contains("show")&&!t.contains(e.target)&&t.classList.remove("show")}))}function isSlotAvailableForSession(e,t,a,r){if(!a||!a.groupe)return!0;let o=a.groupe.trim().split(/\s+/).filter(Boolean),l=a.salle;for(let n in timetableData){if(n===r)continue;let s=timetableData[n][e][t];if(!s||!s.groupe)continue;if(l&&"TEAMS"!==l&&s.salle===l)return!1;let i=s.groupe.trim().split(/\s+/).filter(Boolean);if(o.some(e=>i.includes(e)))return!1}return!0}function getModuleSemester(e,t,a){if(!e||!t||!a)return null;let r=t.trim().split(/\s+/).filter(Boolean);for(let o of r){let l=appData.affectations.find(t=>t.formateur===e&&t.groupe===o&&t.module===a);if(l){let n=parseFloat(l.s1_heures)||0,s=parseFloat(l.s2_heures)||0;if(n>0&&s>0)return"Annual";if(n>0)return"S1";if(s>0)return"S2"}}return null}function calculateAndShowGroupHours(e){let t={};for(let a in e)for(let r in e[a])for(let o in e[a][r]){let l=e[a][r][o];if(l.groupe){let n=l.groupe.trim().split(/\s+/).filter(Boolean);n.forEach(e=>{t[e]=(t[e]||0)+2.5})}}let s=Object.entries(t).map(([e,t])=>({groupe:e,heures:t})).sort((e,t)=>t.heures-e.heures),i=document.getElementById("groupHoursResultTbody");if(i.innerHTML="",0===s.length)i.innerHTML='<tr><td colspan="2" style="text-align:center;">Aucune heure n\'a \xe9t\xe9 affect\xe9e.</td></tr>';else{let c=[...s].sort((e,t)=>e.groupe.localeCompare(t.groupe));c.forEach(({groupe:e,heures:t})=>{i.innerHTML+=`<tr><td>${e}</td><td>${t.toFixed(1)} h</td></tr>`})}let u=document.getElementById("groupHoursChartCanvas").getContext("2d"),d=s.map(e=>e.groupe),p=s.map(e=>e.heures);groupHoursChart&&groupHoursChart.destroy(),groupHoursChart=new Chart(u,{type:"bar",data:{labels:d,datasets:[{label:"Heures par Groupe",data:p,backgroundColor:"rgba(54, 162, 235, 0.6)",borderColor:"rgba(54, 162, 235, 1)",borderWidth:1}]},options:{responsive:!0,maintainAspectRatio:!1,scales:{y:{beginAtZero:!0,title:{display:!0,text:"Nombre d'heures"}}},plugins:{legend:{display:!1},title:{display:!0,text:"",font:{size:16}},datalabels:{anchor:"end",align:"top",offset:4,color:"#444",font:{weight:"bold"},formatter:(e,t)=>e.toFixed(1)+" h"}}}});let f=document.getElementById("groupHoursModalTitle"),m="";if(currentWeekValue&&currentWeekValue.includes("-W")){let g=currentWeekValue.split("-W")[1];g&&(m=` - S${g}`)}f.textContent=`Masse Horaire par Groupe${m}`,document.getElementById("groupHoursModal").style.display="block"}document.addEventListener("DOMContentLoaded",()=>{checkSessionAndLoad(),document.getElementById("notificationClose").addEventListener("click",()=>{document.getElementById("notification").classList.add("hide")})});