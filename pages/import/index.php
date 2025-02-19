<!DOCTYPE html>
<html>
<head>
    <title>數據導入</title>
    <meta charset="UTF-8">
    <script src="https://cdn.jsdelivr.net/npm/ag-grid-enterprise@30.1.0/dist/ag-grid-enterprise.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community/styles/ag-grid.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community/styles/ag-theme-alpine.css">
    <script src="https://unpkg.com/xlsx/dist/xlsx.full.min.js"></script>
    <script>
    agGrid.LicenseManager.setLicenseKey("DownloadDevTools_COM_NDEwMjM0NTgwMDAwMA==59158b5225400879a12a96634544f5b6");
    </script>
    <style>
        .container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .controls {
            margin-bottom: 20px;
        }
        .controls > * {
            margin-right: 10px;
            margin-bottom: 10px;
        }
        #myGrid {
            height: 600px;
            width: 100%;
        }
        .file-inputs {
            display: none;
        }
        .button {
            padding: 8px 15px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .button:hover {
            background: #45a049;
        }
        select {
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>數據導入</h1>
        
        <div class="controls">
            <select id="tableSelect" onchange="handleTableChange()">
                <option value="students">學生資料</option>
                <option value="book_info">借書記錄</option>
                <option value="book_info2">書籍資料</option>
            </select>

            <input type="file" id="excelFile" accept=".xlsx,.xls" class="file-inputs">
            <button class="button" onclick="document.getElementById('excelFile').click()">選擇EXCEL文件</button>

            <input type="file" id="csvFile" accept=".csv" class="file-inputs">
            <button class="button" onclick="document.getElementById('csvFile').click()">選擇CSV文件</button>

            <button class="button" onclick="selectAll()">全選</button>
            <button class="button" onclick="deselectAll()">反選</button>
            <button class="button" onclick="importData()">導入</button>
            <button class="button" onclick="updateSelectedRows()">更新選定的行</button>
        </div>

        <div id="myGrid" class="ag-theme-alpine"></div>
        <div id="sqlDisplay" style="margin-top: 20px; padding: 15px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; white-space: pre-wrap; display: none;">
        </div>
    </div>

    <script>
        // Function to update SQL display
        function updateSqlDisplay(sql, success = true) {
            const sqlDisplay = document.getElementById('sqlDisplay');
            if (sql) {
                sqlDisplay.style.display = 'block';
                sqlDisplay.style.borderColor = success ? '#4CAF50' : '#f44336';
                sqlDisplay.innerHTML = sql;
            } else {
                sqlDisplay.style.display = 'none';
                sqlDisplay.innerHTML = '';
            }
        }

        let gridApi;
        let columnDefs = [];
        const tableColumns = {
            students: [
                {field: 'id', headerName: '序號', checkboxSelection: true, headerCheckboxSelection: true, width: 100, pinned: 'left'},
                {field: 'section', headerName: '學部', width: 120},
                {field: 'name', headerName: '顯示姓名', width: 120},
                {field: 'name_cn', headerName: '中文姓名', width: 120},
                {field: 'name_en', headerName: '英文姓名', width: 120},
                {field: 'class', headerName: '班級', width: 120},
                {field: 'class_type', headerName: '班別', width: 120},
                {field: 'student_number', headerName: '學號', width: 120},
                {field: 'student_card_number', headerName: '學生卡號', width: 120},
                {field: 'student_id_number', headerName: '學生證編號', width: 120},
                {field: 'school_card_number', headerName: '學校學生卡編號', width: 120},
                {field: 'enrollment_year', headerName: '入學年份', width: 120},
                {field: 'valid_or_not', headerName: '有效或冇效', width: 120},
                {field: 'student_group', headerName: '學生組別', width: 120},
                {field: 'password', headerName: '密碼', width: 120},
                {field: 'unitid', headerName: '功能名稱', width: 120},
                {field: 'role', headerName: '角色(1老師/0學生/2副校長/3校長)', width: 120},
                {field: 'SEN', headerName: 'SEN', width: 120},
                {field: 'late_return_book', headerName: '遲還書次數', width: 120},
                {field: 'image_url', headerName: '圖片網址', width: 120}
            ],
            book_info: [
                {field: 'id', headerName: 'ID', checkboxSelection: true, headerCheckboxSelection: true, width: 100, pinned: 'left'},
                {field: 'isbn', headerName: 'ISBN', width: 120},
                {field: 'cal_no', headerName: '索書號', width: 120},
                {field: 'title', headerName: '書名', width: 200},
                {field: 'author', headerName: '作者', width: 120},
                {field: 'publisher', headerName: '出版社', width: 150},
                {field: 'published_date', headerName: '出版日期', width: 120},
                {field: 'available', headerName: '可借閱狀態', width: 120},
                {field: 'borrow_date', headerName: '借出日期', width: 120},
                {field: 'return_date', headerName: '歸還日期', width: 120},
                {field: 'student_card_number', headerName: '借出人卡號', width: 120},
                {field: 'role', headerName: '借出人身份', width: 120},
                {field: 'borrow_day', headerName: '借出天數', width: 120}
            ],
            book_info2: [
                {field: 'id', headerName: 'ID', checkboxSelection: true, headerCheckboxSelection: true, width: 100, pinned: 'left'},
                {field: 'isbn', headerName: 'ISBN', width: 120},
                {field: 'cal_no', headerName: '索書號', width: 120},
                {field: 'title', headerName: '書名', width: 200},
                {field: 'author', headerName: '作者', width: 120},
                {field: 'publisher', headerName: '出版社', width: 150},
                {field: 'published_date', headerName: '出版日期', width: 120},
                {field: 'available', headerName: '可借閱狀態', width: 120},
                {field: 'borrow_time', headerName: '借出次數', width: 120},
                {field: 'image_url', headerName: '圖片網址', width: 120} 
            ]
        };

        // Initialize AG-Grid
        const gridOptions = {
            defaultColDef: {
                flex: 1,
                minWidth: 100,
                editable: true,
                resizable: true,
                sortable: true,
                filter: true
            },
            rowSelection: 'multiple',
            animateRows: true,
            onGridReady: (params) => {
                gridApi = params.api;
                handleTableChange();
                gridApi.sizeColumnsToFit();
            },
            components: {
                datePicker: getDatePicker()
            },
            getRowId: params => params.data.id
        };

        // Initialize the grid
        const gridDiv = document.querySelector('#myGrid');
        new agGrid.Grid(gridDiv, gridOptions);

        // Handle table change
        function handleTableChange() {
            const selectedTable = document.getElementById('tableSelect').value;
            gridOptions.api.setColumnDefs(tableColumns[selectedTable]);
            gridOptions.api.setRowData([]);
        }

        // Handle file selection for both Excel and CSV
        document.getElementById('excelFile').addEventListener('change', handleExcelFile);
        document.getElementById('csvFile').addEventListener('change', handleCsvFile);

        function selectAll() {
            gridOptions.api.selectAll();
        }

        function deselectAll() {
            const selectedNodes = gridOptions.api.getSelectedNodes();
            const allNodes = [];
            gridOptions.api.forEachNode((node) => allNodes.push(node));
            
            allNodes.forEach(node => {
                if (selectedNodes.includes(node)) {
                    node.setSelected(false);
                } else {
                    node.setSelected(true);
                }
            });
        }

        // Handle Excel file using SheetJS
        function handleExcelFile(event) {
            const file = event.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(e) {
                const data = new Uint8Array(e.target.result);
                const workbook = XLSX.read(data, {type: 'array'});
                const sheetName = workbook.SheetNames[0];
                const worksheet = workbook.Sheets[sheetName];
                const jsonData = XLSX.utils.sheet_to_json(worksheet, {header: 1});

                // Map Excel headers to our field names
                const headerMap = {
                    '學號': 'student_number',
                    '姓名': 'name',
                    '學部': 'section',
                    '班級': 'class',
                    '班別': 'class_type',
                    '學生卡號': 'student_card_number',
                    '學校卡號': 'school_card_number',
                    '入學年份': 'enrollment_year',
                    '是否有效': 'valid_or_not',
                    '學生組別': 'student_group',
                    '角色': 'role',
                    '遲還書次數': 'late_return_book'
                };

                const headers = jsonData[0].map(header => headerMap[header] || header);
                const result = [];

                for (let i = 1; i < jsonData.length; i++) {
                    const row = jsonData[i];
                    const obj = {};
                    for (let j = 0; j < headers.length; j++) {
                        obj[headers[j]] = row[j];
                    }
                    result.push(obj);
                }

                gridOptions.api.setRowData(result);
            };
            reader.readAsArrayBuffer(file);
        }

        // Handle CSV file with proper encoding support
        function handleCsvFile(event) {
            const file = event.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const csvData = e.target.result;
                    const lines = csvData.split('\n');
                    
                    // Parse CSV line considering quotes
                    function parseCSVLine(line) {
                        const result = [];
                        let inQuotes = false;
                        let currentValue = '';
                        
                        for(let i = 0; i < line.length; i++) {
                            const char = line[i];
                            
                            if(char === '"') {
                                inQuotes = !inQuotes;
                                continue;
                            }
                            
                            if(char === ',' && !inQuotes) {
                                result.push(currentValue.trim());
                                currentValue = '';
                                continue;
                            }
                            
                            currentValue += char;
                        }
                        
                        if(currentValue) {
                            result.push(currentValue.trim());
                        }
                        
                        return result;
                    }
                    
                    const headers = parseCSVLine(lines[0]);
                    
                    // Map CSV headers to our field names
                    const headerMap = {
                        // 中文欄位映射
                        '學號': 'student_number',
                        '姓名': 'name',
                        '學部': 'section',
                        '班級': 'class',
                        '班別': 'class_type',
                        'student_number': 'student_number',
                        'name': 'name',
                        'section': 'section',
                        'class': 'class',
                        'class_type': 'class_type',
                        'student_card_number': 'student_card_number',
                        'school_card_number': 'school_card_number',
                        'enrollment_year': 'enrollment_year',
                        'valid_or_not': 'valid_or_not',
                        'student_group': 'student_group',
                        'role': 'role',
                        'late_return_book': 'late_return_book',
                        'student_id_number': 'student_id_number',
                        'name_en': 'name_en',
                        'name_cn': 'name_cn',
                        'unitid': 'unitid',
                        'SEN': 'SEN',
                        'password': 'password',
                        'id': 'id'
                    };

                    const result = lines.slice(1)
                        .filter(line => line.trim())
                        .map(line => {
                            console.log('Processing line:', line);
                            const values = parseCSVLine(line);
                            const obj = {};
                            headers.forEach((header, index) => {
                                const cleanHeader = header.replace(/^"|"$/g, '');
                                const mappedHeader = headerMap[cleanHeader] || cleanHeader;
                                let value = values[index] || '';
                                value = value.replace(/^"|"$/g, '').trim();
                                
                                // Handle different field types
                                switch(mappedHeader) {
                                    case 'enrollment_year':
                                        obj[mappedHeader] = value ? parseInt(value) : null;
                                        break;
                                    case 'valid_or_not':
                                    case 'role':
                                    case 'late_return_book':
                                    case 'SEN':
                                        obj[mappedHeader] = value ? parseInt(value) : 0;
                                        break;
                                    case 'id':
                                        // Skip id field
                                        break;
                                    default:
                                        obj[mappedHeader] = value;
                                }
                            });
                            
                            // Skip empty rows
                            const hasValues = Object.values(obj).some(val => val !== null && val !== '');
                            return hasValues ? obj : null;
                        });

                    gridOptions.api.setRowData(result);
                } catch (error) {
                    console.error('CSV處理錯誤:', error);
                    // Try again with BIG5 encoding
                    const retryReader = new FileReader();
                    retryReader.onload = function(e) {
                        try {
                            const csvData = e.target.result;
                            const lines = csvData.split('\n');
                            const headers = parseCSVLine(lines[0]);
                            
                            const result = lines.slice(1)
                                .filter(line => line.trim())
                                .map(line => {
                                    console.log('Processing line (BIG5):', line);
                                    const values = parseCSVLine(line);
                                    const obj = {};
                                    headers.forEach((header, index) => {
                                        const cleanHeader = header.replace(/^"|"$/g, '');
                                        const mappedHeader = headerMap[cleanHeader] || cleanHeader;
                                        let value = values[index] || '';
                                        value = value.replace(/^"|"$/g, '').trim();
                                        
                                        // Handle different field types
                                        switch(mappedHeader) {
                                            case 'enrollment_year':
                                                obj[mappedHeader] = value ? parseInt(value) : null;
                                                break;
                                            case 'valid_or_not':
                                            case 'role':
                                            case 'late_return_book':
                                            case 'SEN':
                                                obj[mappedHeader] = value ? parseInt(value) : 0;
                                                break;
                                            case 'id':
                                                // Skip id field
                                                break;
                                            default:
                                                obj[mappedHeader] = value;
                                        }
                                    });
                                    
                                    // Skip empty rows
                                    const hasValues = Object.values(obj).some(val => val !== null && val !== '');
                                    return hasValues ? obj : null;
                                });

                            gridOptions.api.setRowData(result);
                        } catch (error) {
                            console.error('CSV處理錯誤 (BIG5):', error);
                            alert('CSV文件格式錯誤');
                        }
                    };
                    retryReader.readAsText(file, 'big5');
                }
            };
            reader.readAsText(file, 'utf-8');
        }

        // Import data
        function importData() {
            const selectedTable = document.getElementById('tableSelect').value;
            const rowData = [];
            gridOptions.api.forEachNode((node) => rowData.push(node.data));

            if (rowData.length === 0) {
                alert('請先導入數據');
                return;
            }

            fetch('update_rows.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    table: selectedTable,
                    data: rowData
                })
            })
            .then(response => response.json())
            .then(result => {
                alert(result.message);
                updateSqlDisplay(result.sql, result.success);
                if (result.success) {
                    gridOptions.api.setRowData([]);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                
                // 首先获取错误响应JSON
                if (error.response && typeof error.response.json === 'function') {
                    error.response.json()
                        .then(data => {
                            let errorMessage = data.message || '導入失敗';
                            if (data.sql) {
                                errorMessage += '\n\nSQL語句:\n' + data.sql;
                            }
                            alert(errorMessage);
                        })
                        .catch(() => {
                            alert('導入失敗 (無法讀取錯誤詳情)');
                        });
                } else {
                    alert('導入失敗 (伺服器無響應)');
                }
            });
        }

        // Update selected rows
        function updateSelectedRows() {
            const selectedTable = document.getElementById('tableSelect').value;
            const selectedRows = gridOptions.api.getSelectedRows();

            if (selectedRows.length === 0) {
                alert('請選擇要更新的行');
                return;
            }

            fetch('update_rows.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    table: selectedTable,
                    data: selectedRows,
                    update: true
                })
            })
            .then(response => response.json())
            .then(result => {
                alert(result.message);
                updateSqlDisplay(result.sql, result.success);
            })
            .catch(error => {
                console.error('Error:', error);
                if (error.response) {
                    error.response.json().then(data => {
                        let errorMessage = '更新失敗';
                        if (data.sql) {
                            errorMessage += '\n\nSQL語句:\n' + data.sql;
                        }
                        alert(errorMessage);
                    });
                } else {
                    alert('更新失敗');
                }
            });
        }

        // Custom date picker component
        function getDatePicker() {
            function Datepicker() {}
            Datepicker.prototype.init = function(params) {
                this.eInput = document.createElement('input');
                this.eInput.value = params.value;
                this.eInput.type = 'date';
                this.eInput.classList.add('ag-input');
                this.eInput.style.height = '100%';
                this.eInput.style.width = '100%';
            };
            Datepicker.prototype.getGui = function() {
                return this.eInput;
            };
            Datepicker.prototype.afterGuiAttached = function() {
                this.eInput.focus();
                this.eInput.select();
            };
            Datepicker.prototype.getValue = function() {
                return this.eInput.value;
            };
            Datepicker.prototype.destroy = function() {};
            Datepicker.prototype.isPopup = function() {
                return false;
            };
            return Datepicker;
        }

        // Add window resize listener
        window.addEventListener('resize', () => {
            if (gridApi) {
                gridApi.sizeColumnsToFit();
            }
        });
    </script>
</body>
</html>
