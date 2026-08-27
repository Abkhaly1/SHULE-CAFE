class AppCsvImportModal extends HTMLElement {
    constructor() {
        super();
        this._entityType = 'general';
        this._parsedRows = [];
        this._conflicts = [];
        this._availableClassrooms = [];
    }

    connectedCallback() {
        this.render();
        this.setupEventListeners();
    }

    async open(entityType = 'general') {
        this._entityType = entityType;
        this.querySelector('#importModalTitle').textContent = `Import ${this.getEntityLabel(entityType)} CSV / Excel Data`;
        this.querySelector('#importInstructions').innerHTML = this.getInstructionsHtml(entityType);
        this.resetState();
        this.style.display = 'flex';

        if (entityType === 'students') {
            await this.fetchClassrooms();
        }
    }

    close() {
        this.style.display = 'none';
        this.resetState();
    }

    resetState() {
        this._parsedRows = [];
        this._conflicts = [];

        this.querySelector('#importAlert').style.display = 'none';
        this.querySelector('#importProgressContainer').style.display = 'none';
        this.querySelector('#conflictSection').style.display = 'none';
        this.querySelector('#classroomSection').style.display = 'none';
        this.querySelector('#importProgressBar').style.width = '0%';
        this.querySelector('#importProgressText').textContent = '0%';
        this.querySelector('#importStatusText').textContent = '';
        this.querySelector('#csvFileInput').value = '';
        this.querySelector('#btnStartImport').disabled = true;
        this.querySelector('#btnStartImport').textContent = 'Review & Start Import ➔';
    }

    async fetchClassrooms() {
        try {
            const res = await fetch('/shule-cafe/api/headmaster/people/import_users.php?action=list_classrooms', { credentials: 'include' });
            const result = await res.json();
            if (res.ok && result.success) {
                this._availableClassrooms = result.classrooms || [];
            }
        } catch (e) {
            this._availableClassrooms = [];
        }
    }

    getEntityLabel(type) {
        const labels = {
            'schools': 'Schools',
            'users': 'Platform Users',
            'teachers': 'Teachers / Staffs',
            'students': 'Students',
            'templates': 'Academic Templates',
            'general': 'Records'
        };
        return labels[type] || 'Records';
    }

    getInstructionsHtml(type) {
        const specs = {
            'teachers': {
                headers: 'Full Name, Reg Code, Department',
                sample: 'Mr. Baraka Test, TCH/202X/001, Mathematics',
                fields: ['Full Name (required)', 'Reg Code / Staff ID (required)', 'Department (optional)']
            },
            'students': {
                headers: 'Full Name, Reg Code',
                sample: 'Baraka Juma Mussa, STD/${new Date().getFullYear()}/001',
                fields: ['Full Name (required)', 'Reg Code / Student Reg No (required)']
            },
            'schools': {
                headers: 'name, type, region, headmaster_name, headmaster_phone',
                sample: 'Mlimani Primary, Primary, Dar es Salaam, Juma Ali, +255711000111',
                fields: ['School Name (required)', 'Type (Primary/Secondary)', 'Region', 'Headmaster Name', 'Headmaster Phone']
            },
            'general': {
                headers: 'Full Name, Reg Code',
                sample: 'Amani Hassan Juma, REG/2026/001',
                fields: ['Full Name', 'Reg Code']
            }
        };

        const spec = specs[type] || specs['general'];
        return `
            <div style="background: #f8fafc; padding: 14px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 13px; margin-bottom: 16px;">
                <p style="margin: 0 0 6px 0; font-weight: 700; color: #1e293b;">📄 CSV / Excel File Guidelines:</p>
                <ul style="margin: 0; padding-left: 18px; color: #475569; line-height: 1.5;">
                    <li>File must be a valid <strong>CSV (.csv)</strong> format.</li>
                    <li>First row must contain Column Headers: <code style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px; color: #0f172a;">${spec.headers}</code></li>
                    <li>Registration numbers (Staff ID / Reg No) will serve as default <strong>Initial Passwords</strong> for each user.</li>
                </ul>
            </div>
        `;
    }

    downloadSampleCsv() {
        const specs = {
            'teachers': "Full Name,Reg Code,Department\r\n\"Mr. Baraka Test\",\"TCH/202X/001\",\"Mathematics\"\r\n\"Ms. Asha Juma\",\"TCH/202X/002\",\"Sciences\"\r\n\"Mr. David John\",\"TCH/202X/003\",\"Languages\"\r\n",
            'students': "Full Name,Reg Code\r\n\"Baraka Juma Mussa\",\"STD/202X/001\"\r\n\"Amani Hassan Juma\",\"STD/202X/002\"\r\n\"Neema Charles Kimaro\",\"STD/202X/003\"\r\n",
            'general': "Full Name,Reg Code\r\n\"Sample Name\",\"REG/202X/001\"\r\n"
        };

        const csvData = specs[this._entityType] || specs['general'];
        const blob = new Blob([csvData], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement("a");
        const url = URL.createObjectURL(blob);
        link.setAttribute("href", url);
        link.setAttribute("download", `sample_import_${this._entityType}_template.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    render() {
        this.style.display = 'none';
        this.style.position = 'fixed';
        this.style.top = '0';
        this.style.left = '0';
        this.style.width = '100vw';
        this.style.height = '100vh';
        this.style.background = 'rgba(15, 23, 42, 0.8)';
        this.style.backdropFilter = 'blur(6px)';
        this.style.webkitBackdropFilter = 'blur(6px)';
        this.style.zIndex = '99999';
        this.style.alignItems = 'center';
        this.style.justifyContent = 'center';
        this.style.padding = '20px';
        this.style.boxSizing = 'border-box';

        this.innerHTML = `
            <div class="import-workspace-card" style="width: 100%; max-width: 1320px; height: 92vh; background: #ffffff; border-radius: 16px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35); border: 1px solid #e2e8f0;">
                <!-- WORKSPACE HEADER -->
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 20px 32px; border-bottom: 1px solid #e2e8f0; background: #ffffff; flex-shrink: 0;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="width: 40px; height: 40px; border-radius: 10px; background: #ecfdf5; color: #047857; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                            📥
                        </div>
                        <div>
                            <h2 id="importModalTitle" style="margin: 0; font-size: 20px; font-weight: 800; color: #0f172a; letter-spacing: -0.3px;">Bulk Data Import Workspace</h2>
                            <div style="font-size: 13px; color: #64748b; margin-top: 2px;">Upload CSV/Excel files, resolve conflicts, and assign target classrooms</div>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <button type="button" id="btnDownloadSample" class="btn btn-outline" style="font-size: 13px; display: inline-flex; align-items: center; gap: 6px; font-weight: 700; padding: 8px 16px; border-radius: 8px;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                            Download Sample CSV
                        </button>
                        <button type="button" id="btnCloseHeaderX" style="background: #f1f5f9; border: none; color: #64748b; font-size: 20px; font-weight: 700; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s;" title="Close Workspace">&times;</button>
                    </div>
                </div>

                <!-- WORKSPACE SCROLLABLE BODY -->
                <div id="importScrollBody" style="flex: 1; overflow-y: auto; padding: 28px 32px; background: #f8fafc;">

                    <div id="importInstructions"></div>

                    <div id="importAlert" class="alert alert-danger" style="display: none; margin-bottom: 20px; font-size: 14px; padding: 14px 18px; border-radius: 10px;"></div>

                    <!-- FILE SELECTION CARD -->
                    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px 24px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(15,23,42,0.03);">
                        <label class="form-label" style="font-weight: 800; font-size: 14px; color: #0f172a; margin-bottom: 8px; display: block;">Select CSV / Excel File</label>
                        <input type="file" id="csvFileInput" class="form-control" accept=".csv, .txt" style="height: 44px; padding: 6px 14px; font-size: 14px;">
                    </div>

                    <!-- PROGRESS BAR SECTION -->
                    <div id="importProgressContainer" style="display: none; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px 24px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(15,23,42,0.03);">
                        <div style="display: flex; justify-content: space-between; font-size: 14px; font-weight: 700; margin-bottom: 8px;">
                            <span id="importStatusText" style="color: #0f172a;">Validating file &amp; database...</span>
                            <span id="importProgressText" style="color: #047857;">0%</span>
                        </div>
                        <div style="width: 100%; height: 12px; background: #e2e8f0; border-radius: 6px; overflow: hidden;">
                            <div id="importProgressBar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #047857, #10b981); transition: width 0.2s ease;"></div>
                        </div>
                    </div>

                    <!-- DUPLICATE CONFLICT PREVIEW SECTION -->
                    <div id="conflictSection" style="display: none; margin-bottom: 24px;">
                        <div style="background: #fffbeb; border: 1px solid #fde68a; padding: 16px; border-radius: 12px; font-size: 14px; color: #92400e; margin-bottom: 16px; display: flex; align-items: center; gap: 10px;">
                            <span style="font-size: 20px;">⚠️</span>
                            <div>
                                <strong>Duplicate &amp; Conflict Analysis:</strong> Detected <strong id="conflictCountNum" style="text-decoration: underline;">0</strong> record(s) that already exist in the system or are duplicated in the file. Choose action:
                            </div>
                        </div>

                        <!-- CONFLICT TABLE WORKSPACE VIEW -->
                        <div id="conflictTableWrapper" style="max-height: 360px; overflow-y: auto; border: 1px solid #cbd5e1; border-radius: 10px; margin-bottom: 16px; background: #ffffff; box-shadow: 0 2px 8px rgba(15,23,42,0.03);">
                            <table style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
                                <thead style="background: #f1f5f9; border-bottom: 2px solid #cbd5e1; position: sticky; top: 0; z-index: 10;">
                                    <tr>
                                        <th style="padding: 10px 14px; width: 80px; color: #475569; font-weight: 800;">Line #</th>
                                        <th style="padding: 10px 14px; width: 220px; color: #0f172a; font-weight: 800;">Uploaded Name</th>
                                        <th style="padding: 10px 14px; width: 160px; color: #0f172a; font-weight: 800;">Reg ID / Code</th>
                                        <th style="padding: 10px 14px; color: #0f172a; font-weight: 800;">Conflict Description</th>
                                    </tr>
                                </thead>
                                <tbody id="conflictTableBody">
                                </tbody>
                            </table>
                        </div>

                        <div style="background: #ffffff; border: 1px solid #e2e8f0; padding: 18px 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(15,23,42,0.03);">
                            <div style="font-weight: 800; font-size: 14px; color: #0f172a; margin-bottom: 10px;">Select Conflict Resolution Policy:</div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                                <label style="display: flex; align-items: flex-start; gap: 10px; font-size: 13px; cursor: pointer; background: #f8fafc; padding: 12px 14px; border: 1px solid #e2e8f0; border-radius: 8px;">
                                    <input type="radio" name="dup_handling" value="skip" checked style="margin-top: 2px;">
                                    <div>
                                        <strong style="color: #0f172a;">⏭️ Skip Existing:</strong>
                                        <div style="color: #64748b; font-size: 12px; margin-top: 2px;">Import new users only and leave existing records unchanged.</div>
                                    </div>
                                </label>
                                <label style="display: flex; align-items: flex-start; gap: 10px; font-size: 13px; cursor: pointer; background: #f8fafc; padding: 12px 14px; border: 1px solid #e2e8f0; border-radius: 8px;">
                                    <input type="radio" name="dup_handling" value="update" style="margin-top: 2px;">
                                    <div>
                                        <strong style="color: #0f172a;">🔄 Update Existing:</strong>
                                        <div style="color: #64748b; font-size: 12px; margin-top: 2px;">Update information of existing users according to the file.</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- STUDENT CLASSROOM ASSIGNMENT SECTION -->
                    <div id="classroomSection" style="display: none; margin-bottom: 24px;">
                        <!-- Dynamically populated based on available classrooms -->
                    </div>

                </div><!-- end scrollable body -->

                <!-- STICKY FOOTER ACTION BAR -->
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 18px 32px; border-top: 1px solid #e2e8f0; flex-shrink: 0; background: #ffffff;">
                    <div style="font-size: 13px; color: #64748b; font-weight: 600;">
                        💡 Review your file formatting and options before completing import.
                    </div>
                    <div style="display: flex; gap: 12px; align-items: center;">
                        <button type="button" id="btnCloseImportModal" class="btn btn-outline" style="padding: 10px 20px; font-weight: 700; border-radius: 8px;">Cancel</button>
                        <button type="button" id="btnStartImport" class="btn btn-primary" style="font-weight: 800; padding: 10px 24px; border-radius: 8px; font-size: 14px;" disabled>Verify &amp; Start Import ➔</button>
                    </div>
                </div>
            </div>
        `;
    }

    renderClassroomPrompt() {
        const container = this.querySelector('#classroomSection');
        if (this._entityType !== 'students') {
            container.style.display = 'none';
            return;
        }

        if (this._availableClassrooms && this._availableClassrooms.length > 0) {
            let optionsHtml = '';
            this._availableClassrooms.forEach(c => {
                const label = c.classroom_name.toLowerCase().startsWith(c.grade_name.toLowerCase()) 
                    ? c.classroom_name 
                    : `${c.grade_name} — ${c.classroom_name}`;
                optionsHtml += `<option value="${c.id}">${label}</option>`;
            });

            container.innerHTML = `
                <div style="background: #ffffff; border: 1px solid #bbf7d0; padding: 20px 22px; border-radius: 12px; box-shadow: 0 2px 8px rgba(15,23,42,0.03);">
                    <div style="font-weight: 800; font-size: 15px; color: #166534; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                        <span>🏫</span> Allocate all newly imported students to a classroom now?
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 12px;">
                        <label style="display: flex; align-items: flex-start; gap: 10px; font-size: 13px; cursor: pointer; background: #f0fdf4; padding: 12px 14px; border: 1px solid #dcfce7; border-radius: 8px;">
                            <input type="radio" name="assign_classroom_choice" value="none" checked id="radioAllocNone" style="margin-top: 2px;">
                            <div>
                                <strong style="color: #166534;">🔘 Keep Unallocated (Unallocated Pool)</strong>
                                <div style="color: #475569; font-size: 12px; margin-top: 2px;">Students will be added to system pool without classroom assignment. You can assign classrooms later.</div>
                            </div>
                        </label>
                        <label style="display: flex; align-items: flex-start; gap: 10px; font-size: 13px; cursor: pointer; background: #f0fdf4; padding: 12px 14px; border: 1px solid #dcfce7; border-radius: 8px;">
                            <input type="radio" name="assign_classroom_choice" value="select" id="radioAllocSelect" style="margin-top: 2px;">
                            <div>
                                <strong style="color: #166534;">🏫 Assign All Students to Selected Classroom</strong>
                                <div style="color: #475569; font-size: 12px; margin-top: 2px;">Directly enroll all imported students into a designated classroom.</div>
                            </div>
                        </label>
                    </div>

                    <div id="classroomSelectWrapper" style="display: none; padding-top: 12px; border-top: 1px dashed #bbf7d0; margin-top: 12px;">
                        <label style="font-weight: 700; font-size: 13px; color: #166534; margin-bottom: 6px; display: block;">Select Target Classroom Stream:</label>
                        <select id="importClassroomSelect" class="form-control" style="font-weight: 700; height: 42px; font-size: 14px; border-color: #86efac;">
                            <option value="">— Select a classroom stream —</option>
                            ${optionsHtml}
                        </select>
                        <div style="font-size: 12px; color: #b91c1c; margin-top: 6px; display: none; font-weight: 700;" id="classroomSelectError">⚠️ Please select a classroom before importing.</div>
                    </div>
                </div>
            `;

            container.style.display = 'block';

            // Add radio change event listener inside modal
            const radioNone = container.querySelector('#radioAllocNone');
            const radioSelect = container.querySelector('#radioAllocSelect');
            const selectWrapper = container.querySelector('#classroomSelectWrapper');

            radioNone.addEventListener('change', () => { selectWrapper.style.display = 'none'; });
            radioSelect.addEventListener('change', () => { selectWrapper.style.display = 'block'; });

        } else {
            container.innerHTML = `
                <div style="background: #eff6ff; border: 1px solid #bfdbfe; padding: 14px; border-radius: 8px; font-size: 13px; color: #1e40af;">
                    💡 <strong>Classrooms Notice:</strong> No classrooms have been created in your school yet. These students will be imported without a classroom (Unallocated Pool). You will need to go to the <strong>Classrooms Workspace</strong> to create classrooms and assign them later.
                </div>
            `;
            container.style.display = 'block';
        }
    }

    parseCsvText(text) {
        const lines = text.split(/\r\n|\n/).map(l => l.trim()).filter(l => l.length > 0);
        if (lines.length <= 1) return [];

        const headers = lines[0].split(',').map(h => h.replace(/^"(.*)"$/, '$1').trim().toLowerCase());
        const rows = [];

        for (let i = 1; i < lines.length; i++) {
            const values = lines[i].split(',').map(v => v.replace(/^"(.*)"$/, '$1').trim());
            const rowObj = {};
            headers.forEach((h, idx) => {
                rowObj[h] = values[idx] || '';
            });

            // Map header synonyms
            const nameVal = rowObj['full name'] || rowObj['full_name'] || rowObj['name'] || rowObj['majina kamili'] || rowObj['jina'] || '';
            const codeVal = rowObj['reg code'] || rowObj['namba ya usajili'] || rowObj['user_code'] || rowObj['staff id'] || rowObj['reg no'] || rowObj['student id'] || rowObj['id'] || '';
            const deptVal = rowObj['department'] || rowObj['idara'] || 'Academics';

            if (nameVal && codeVal) {
                rows.push({
                    full_name: nameVal,
                    user_code: codeVal,
                    department: deptVal
                });
            }
        }

        return rows;
    }

    setupEventListeners() {
        const closeBtn = this.querySelector('#btnCloseImportModal');
        const headerCloseBtn = this.querySelector('#btnCloseHeaderX');
        const sampleBtn = this.querySelector('#btnDownloadSample');
        const fileInput = this.querySelector('#csvFileInput');
        const startBtn = this.querySelector('#btnStartImport');
        const alertBox = this.querySelector('#importAlert');

        if (headerCloseBtn) headerCloseBtn.addEventListener('click', () => this.close());
        closeBtn.addEventListener('click', () => this.close());
        sampleBtn.addEventListener('click', () => this.downloadSampleCsv());

        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            startBtn.disabled = !file;
        });

        let isConflictState = false;

        startBtn.addEventListener('click', async () => {
            const file = fileInput.files[0];
            if (!file && this._parsedRows.length === 0) return;

            alertBox.style.display = 'none';

            const progressContainer = this.querySelector('#importProgressContainer');
            const progressBar = this.querySelector('#importProgressBar');
            const progressText = this.querySelector('#importProgressText');
            const statusText = this.querySelector('#importStatusText');
            const conflictSection = this.querySelector('#conflictSection');

            // STAGE 1: READ AND DETECT DUPLICATES
            if (!isConflictState) {
                const reader = new FileReader();
                reader.onload = async (evt) => {
                    const text = evt.target.result;
                    const rows = this.parseCsvText(text);

                    if (rows.length === 0) {
                        alertBox.textContent = 'The CSV file contains no data or is incorrectly formatted. Ensure "Full Name" and "Reg Code" columns exist.';
                        alertBox.style.display = 'block';
                        return;
                    }

                    this._parsedRows = rows;
                    startBtn.disabled = true;
                    progressContainer.style.display = 'block';

                    statusText.textContent = 'Checking for existing duplicate accounts...';
                    progressBar.style.width = '50%';
                    progressText.textContent = '50%';

                    try {
                        const targetRole = (this._entityType === 'students') ? 'student' : 'teacher';
                        const res = await fetch('/shule-cafe/api/headmaster/people/import_users.php?action=detect_duplicates', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            credentials: 'include',
                            body: JSON.stringify({ role: targetRole, rows: rows })
                        });

                        const result = await res.json();
                        progressBar.style.width = '100%';
                        progressText.textContent = '100%';

                        if (res.ok && result.success) {
                            isConflictState = true;
                            if (result.conflict_count > 0) {
                                this._conflicts = result.conflicts;
                                this.querySelector('#conflictCountNum').textContent = result.conflict_count;
                                
                                const tbody = this.querySelector('#conflictTableBody');
                                tbody.innerHTML = '';
                                result.conflicts.forEach(c => {
                                    const msg = c.conflict ? c.conflict.message : 'Account already exists';
                                    tbody.innerHTML += `
                                        <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;">
                                            <td style="padding: 10px 14px; font-weight: 800; color: #475569;">#${c.row_index}</td>
                                            <td style="padding: 10px 14px; font-weight: 700; color: #0f172a;">${c.full_name}</td>
                                            <td style="padding: 10px 14px; font-family: monospace; font-size: 12px; color: #047857; font-weight: 800;">${c.user_code}</td>
                                            <td style="padding: 10px 14px; color: #92400e; font-weight: 600;">${msg}</td>
                                        </tr>
                                    `;
                                });
                                conflictSection.style.display = 'block';
                            }
                            
                            // Render classroom prompt for students
                            this.renderClassroomPrompt();

                            // Auto-scroll to reveal review sections
                            const scrollBody = this.querySelector('#importScrollBody');
                            if (scrollBody) setTimeout(() => { scrollBody.scrollTop = scrollBody.scrollHeight; }, 50);

                            startBtn.disabled = false;
                            startBtn.textContent = 'Complete User Import ➔';
                            statusText.textContent = `Validation complete. Review options below and click Complete.`;
                        } else {
                            alertBox.textContent = result.message || 'Error occurred while validating data.';
                            alertBox.style.display = 'block';
                            startBtn.disabled = false;
                        }
                    } catch (err) {
                        alertBox.textContent = 'Network error occurred while validating data.';
                        alertBox.style.display = 'block';
                        startBtn.disabled = false;
                    }
                };
                reader.readAsText(file);
            } else {
                // STAGE 2: EXECUTE BATCH IMPORT WITH SELECTED DUPLICATE HANDLING & CLASSROOM SELECTION
                const dupRadio = this.querySelector('input[name="dup_handling"]:checked');
                const dupHandling = dupRadio ? dupRadio.value : 'skip';

                let selectedClassroomId = 0;
                if (this._entityType === 'students') {
                    const allocRadio = this.querySelector('input[name="assign_classroom_choice"]:checked');
                    if (allocRadio && allocRadio.value === 'select') {
                        const selectEl = this.querySelector('#importClassroomSelect');
                        const errEl    = this.querySelector('#classroomSelectError');
                        if (selectEl) {
                            selectedClassroomId = parseInt(selectEl.value, 10) || 0;
                        }
                        // Block import if radio says 'select' but no classroom chosen
                        if (!selectedClassroomId) {
                            if (errEl) errEl.style.display = 'block';
                            startBtn.disabled = false;
                            return;
                        }
                        if (errEl) errEl.style.display = 'none';
                    }
                }

                await this.executeImportBatch(dupHandling, selectedClassroomId);
            }
        });
    }

    async executeImportBatch(dupHandling, classroomId = 0) {
        const startBtn = this.querySelector('#btnStartImport');
        const alertBox = this.querySelector('#importAlert');
        const statusText = this.querySelector('#importStatusText');
        const targetRole = (this._entityType === 'students') ? 'student' : 'teacher';

        startBtn.disabled = true;
        statusText.textContent = 'Importing user accounts into the database...';

        try {
            const res = await fetch('/shule-cafe/api/headmaster/people/import_users.php?action=execute_import', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    role: targetRole,
                    rows: this._parsedRows,
                    duplicate_handling: dupHandling,
                    classroom_id: classroomId
                })
            });

            const result = await res.json();

            if (res.ok && result.success) {
                const summary = result.summary || {};
                let allocMsg = '';
                if (summary.allocated > 0) {
                    allocMsg = `• Students Allocated to Classroom: ${summary.allocated}\n`;
                } else if (targetRole === 'student') {
                    allocMsg = `• Students imported without a classroom. Assign them later in the Classrooms Workspace.\n`;
                }

                alert(`🎉 User import completed successfully!\n\n` +
                      `• New Accounts Created: ${summary.inserted || 0}\n` +
                      `• Accounts Updated: ${summary.updated || 0}\n` +
                      `• Skipped (Duplicates): ${summary.skipped || 0}\n` +
                      allocMsg + `\n` +
                      `New users can now log in using:\n` +
                      `• Username: Full Name or Reg Code / Staff ID\n` +
                      `• Initial Password: The Reg Code / Staff ID itself.\n\n` +
                      `Upon first login, the system will prompt them to set a New Password and update contact details.`);
                this.close();
                window.location.reload();
            } else {
                alertBox.textContent = result.message || 'Error occurred while importing data.';
                alertBox.style.display = 'block';
                startBtn.disabled = false;
            }
        } catch (err) {
            alertBox.textContent = 'Network error occurred while importing data.';
            alertBox.style.display = 'block';
            startBtn.disabled = false;
        }
    }
}

customElements.define('app-csv-import-modal', AppCsvImportModal);
