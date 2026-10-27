// save logic with event listeners

class StreamlinedSave {
    constructor(tableName, requiredFields) {
        this.tableName = tableName;
        this.requiredFields = requiredFields;
        this.savedRows = 0;
        this.autoSaveTimeout = null;
        this.debounceDelay = 1000; // 1 second delay for autosave
        
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.updateSavedCount();
    }

    setupEventListeners() {
        // add event listeners to all input and select elements
        const inputs = document.querySelectorAll('input[data-row], select[data-row]');
        
        inputs.forEach(input => {
            // real time validation and auto save
            input.addEventListener('input', (e) => {
                this.handleInputChange(e);
            });

            input.addEventListener('blur', (e) => {
                this.handleInputBlur(e);
            });

            // enter key to save
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.saveRowFromInput(e.target);
                }
            });
        });

    }

    handleInputChange(e) {
        const rowIndex = e.target.getAttribute('data-row');
        if (!rowIndex) return;

        // clear existing timeout
        if (this.autoSaveTimeout) {
            clearTimeout(this.autoSaveTimeout);
        }

        // set new timeout for autosave
        this.autoSaveTimeout = setTimeout(() => {
            this.checkAndSaveRow(rowIndex);
        }, this.debounceDelay);

        //  validation feedback
        this.validateField(e.target);
    }

    handleInputBlur(e) {
        const rowIndex = e.target.getAttribute('data-row');
        if (!rowIndex) return;

        // save immediately on blur if requirements are met
        this.checkAndSaveRow(rowIndex);
    }

    validateField(input) {
        const fieldName = input.getAttribute('data-col');
        const isRequired = this.requiredFields.includes(fieldName);
        
        if (isRequired && !input.value.trim()) {
            input.style.borderColor = '#dc3545';
            input.style.backgroundColor = '#f8d7da';
        } else {
            input.style.borderColor = '';
            input.style.backgroundColor = '';
        }
    }

    checkAndSaveRow(rowIndex) {
        const rowData = this.getRowData(rowIndex);
        const isValid = this.validateRowData(rowData);
        
        if (isValid) {
            this.saveRow(rowData, rowIndex);
        } else {
            this.showValidationMessage(rowData);
        }
    }

    getRowData(rowIndex) {
        const inputs = document.querySelectorAll(`input[data-row='${rowIndex}'], select[data-row='${rowIndex}']`);
        const rowData = [];
        
        inputs.forEach(input => {
            rowData.push(input.value.trim());
        });
        
        return rowData;
    }

    validateRowData(rowData) {
        // check if all required fields are filled
        for (let i = 0; i < this.requiredFields.length; i++) {
            const fieldName = this.requiredFields[i];
            const fieldIndex = this.getFieldIndex(fieldName);
            
            if (fieldIndex !== -1 && (!rowData[fieldIndex] || rowData[fieldIndex].trim() === '')) {
                return false;
            }
        }
        
        return true;
    }

    getFieldIndex(fieldName) {
        // map field names to column indices based on table structure
        const fieldMappings = {
            'master_register': {
                'file_number': 0, 'full_name': 1, 'designation': 2, 'status': 5
            },
            'pensioners_register': {
                'file_number': 0, 'full_name': 1, 'designation': 2, 'date_opened': 3
            },
            'contractstaff_register': {
                'file_number': 0, 'name_of_officer': 1, 'title': 2, 'status': 3
            },
            'semicurrent_records': {
                'file_reference_number': 0, 'file_title': 1, 'date_opened': 2, 'action_date': 6, 'box_no': 9
            },
            'file_diary': {
                'file_ref_number': 0, 'address_contact': 1, 'file_title': 2, 'date_opened': 3
            },
            'subject_index': {
                'current_file_title': 2, 'current_index': 3, 'status': 5
            }
        };

        return fieldMappings[this.tableName]?.[fieldName] ?? -1;
    }

    showValidationMessage(rowData) {
        const missingFields = [];
        
        for (let i = 0; i < this.requiredFields.length; i++) {
            const fieldName = this.requiredFields[i];
            const fieldIndex = this.getFieldIndex(fieldName);
            
            if (fieldIndex !== -1 && (!rowData[fieldIndex] || rowData[fieldIndex].trim() === '')) {
                missingFields.push(fieldName.replace('_', ' '));
            }
        }

        const message = `Please fill required fields: ${missingFields.join(', ')}`;
        this.showMessage(message, 'warning');
    }

    async saveRow(rowData, rowIndex) {
        try {
            const response = await fetch('streamlined_save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    table: this.tableName, 
                    row: rowData 
                })
            });

            const result = await response.json();

            if (result.success) {
                this.onSaveSuccess(rowIndex, result);
            } else {
                this.onSaveError(result.error);
            }
        } catch (error) {
            this.onSaveError('Network error: ' + error.message);
        }
    }

    onSaveSuccess(rowIndex, result) {
        // highlight the saved row
        const inputs = document.querySelectorAll(`input[data-row='${rowIndex}'], select[data-row='${rowIndex}']`);
        inputs.forEach(input => {
            input.parentElement.parentElement.classList.add('row-saved');
        });

        // update saved count
        this.savedRows++;
        this.updateSavedCount();

        // show success message
        this.showMessage('Row saved successfully!', 'success');

        // remove highlight after 3 seconds
        setTimeout(() => {
            inputs.forEach(input => {
                input.parentElement.parentElement.classList.remove('row-saved');
            });
        }, 3000);

        // add row ID if returned
        if (result.id) {
            inputs.forEach(input => {
                input.setAttribute('data-row-id', result.id);
            });
        }
    }

    onSaveError(error) {
        this.showMessage('Save failed: ' + error, 'error');
    }

    showMessage(message, type) {
        // remove existing messages
        const existingMessage = document.getElementById('saveMessage');
        if (existingMessage) {
            existingMessage.remove();
        }

        // create new message
        const messageDiv = document.createElement('div');
        messageDiv.id = 'saveMessage';
        messageDiv.className = `alert alert-${type === 'success' ? 'success' : type === 'warning' ? 'warning' : 'danger'} alert-dismissible fade show`;
        messageDiv.style.position = 'fixed';
        messageDiv.style.top = '20px';
        messageDiv.style.right = '20px';
        messageDiv.style.zIndex = '9999';
        messageDiv.style.minWidth = '300px';
        
        messageDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        document.body.appendChild(messageDiv);

        // remove after 5 seconds
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.remove();
            }
        }, 5000);
    }

    updateSavedCount() {
        const savedCountElement = document.getElementById('savedCount');
        if (savedCountElement) {
            savedCountElement.textContent = this.savedRows;
        }
    }

    saveAllRows() {
        const allRows = new Set();
        const inputs = document.querySelectorAll('input[data-row], select[data-row]');
        
        inputs.forEach(input => {
            const rowIndex = input.getAttribute('data-row');
            if (rowIndex) {
                allRows.add(rowIndex);
            }
        });

        let savedCount = 0;
        let errorCount = 0;

        allRows.forEach(rowIndex => {
            const rowData = this.getRowData(rowIndex);
            if (this.validateRowData(rowData)) {
                this.saveRow(rowData, rowIndex).then(() => {
                    savedCount++;
                }).catch(() => {
                    errorCount++;
                });
            }
        });

        this.showMessage(`Saving ${allRows.size} rows...`, 'info');
    }

    // method to manually save a specific row
    saveRowFromInput(input) {
        const rowIndex = input.getAttribute('data-row');
        if (rowIndex) {
            this.checkAndSaveRow(rowIndex);
        }
    }
}

// auto initialize based on current page
document.addEventListener('DOMContentLoaded', function() {
    // determine table name and required fields based on current page
    const path = window.location.pathname;
    let tableName = '';
    let requiredFields = [];

    if (path.includes('masterRegister_edit.php')) {
        tableName = 'master_register';
        requiredFields = ['file_number', 'full_name', 'designation', 'status'];
    } else     if (path.includes('pensionersRegister_edit.php')) {
        tableName = 'pensioners_register';
        requiredFields = ['file_number', 'full_name', 'designation', 'date_opened'];
    } else if (path.includes('contractstaffRegister_edit.php')) {
        tableName = 'contractstaff_register';
        requiredFields = ['file_number', 'name_of_officer', 'title', 'status'];
    } else if (path.includes('semiCurrent_edit.php')) {
        tableName = 'semicurrent_records';
        requiredFields = ['file_reference_number', 'file_title', 'date_opened', 'action_date', 'box_no'];
    } else if (path.includes('filediary_edit.php')) {
        tableName = 'file_diary';
        requiredFields = ['file_ref_number', 'file_title', 'date_opened'];
    } else if (path.includes('subjectIndex_edit.php')) {
        tableName = 'subject_index';
        requiredFields = ['current_file_title', 'current_index', 'status'];
    }

    if (tableName && requiredFields.length > 0) {
        window.streamlinedSave = new StreamlinedSave(tableName, requiredFields);
    }
});
