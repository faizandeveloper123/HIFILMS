<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Staff Directory';

$designations = [];
$res = db_query("SELECT DISTINCT designation FROM employees WHERE status=1 AND designation IS NOT NULL AND designation<>'' ORDER BY designation");
while ($row = $res->fetch_assoc()) { $designations[] = $row['designation']; }
if (empty($designations)) { $designations = ['Admin', 'N/A', 'Teacher', 'Accountant', 'Admin Asst.', 'Administrator', 'Academia Manager']; }

$departments = [];
$res = db_query("SELECT DISTINCT department FROM employees WHERE status=1 AND department IS NOT NULL AND department<>'' ORDER BY department");
while ($row = $res->fetch_assoc()) { $departments[] = $row['department']; }
if (empty($departments)) { $departments = ['Accountant', 'N/A', 'Biology Department', 'Accounts', 'Academic Department']; }

$staff = [];
$res = db_query("SELECT emp_id, first_name, last_name, designation, department, phone FROM employees WHERE status=1 ORDER BY first_name");
while ($row = $res->fetch_assoc()) {
    $staff[] = [
        'id'          => (int) $row['emp_id'],
        'name'        => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
        'designation' => $row['designation'] ?: 'N/A',
        'department'  => $row['department'] ?: 'N/A',
        'contact'     => $row['phone'] ?: '-',
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Directory UI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap');
        body {
            font-family: 'Open Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #333333;
            background-color: #ffffff;
        }
        /* Custom select arrow styling */
        .custom-select {
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%3c555555' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1em;
            appearance: none;
        }
    </style>
</head>
<body class="p-4 md:p-8 min-h-screen bg-white">

    <div class="max-w-7xl mx-auto space-y-6">

        <!-- Top Controls Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4 pb-2">

            <!-- Filters Container -->
            <div class="flex flex-col sm:flex-row gap-4 sm:gap-6 w-full md:w-auto">
                <!-- Designation Filter -->
                <div class="w-full sm:w-64">
                    <label for="designationSelect" class="block text-sm font-semibold text-gray-700 mb-1.5">Designation</label>
                    <select id="designationSelect" onchange="filterData()" class="custom-select w-full bg-white border border-gray-300 rounded px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 transition-colors">
                        <option value="All">All</option>
                        <?php foreach ($designations as $d): ?>
                            <option value="<?php echo e($d); ?>"><?php echo e($d); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Department Filter -->
                <div class="w-full sm:w-64">
                    <label for="departmentSelect" class="block text-sm font-semibold text-gray-700 mb-1.5">Department</label>
                    <select id="departmentSelect" onchange="filterData()" class="custom-select w-full bg-white border border-gray-300 rounded px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 transition-colors">
                        <option value="All">All</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo e($d); ?>"><?php echo e($d); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Watch Demo Button -->
            <div>
                <button onclick="toggleModal(true)" class="bg-[#21b799] hover:bg-[#1ca388] text-white font-medium px-5 py-2.5 rounded-md text-sm flex items-center gap-2.5 transition-all shadow-sm active:scale-95">
                    <i class="fa-solid fa-play text-xs"></i>
                    <span>Watch Demo</span>
                </button>
            </div>
        </div>

        <!-- Data Table Container -->
        <div class="overflow-x-auto border border-gray-200 rounded-sm">
            <table class="w-full text-left border-collapse min-w-[700px]">
                <!-- Table Header -->
                <thead>
                    <tr class="bg-black text-white text-sm font-semibold">
                        <th class="p-3 w-12 text-center border-r border-gray-800">
                            <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)" class="w-4 h-4 accent-emerald-600 rounded cursor-pointer align-middle">
                        </th>
                        <th class="p-3 font-semibold border-r border-gray-800">Staff Name</th>
                        <th class="p-3 font-semibold border-r border-gray-800">Designation</th>
                        <th class="p-3 font-semibold border-r border-gray-800">Department</th>
                        <th class="p-3 font-semibold">Contact</th>
                    </tr>
                </thead>

                <tbody id="staffTableBody" class="text-sm text-gray-700 divide-y divide-gray-200">
                    <!-- Dynamic Rows Populated via JavaScript -->
                </tbody>
            </table>
        </div>

        <!-- Selection Counter Footer -->
        <div class="flex justify-between items-center text-xs text-gray-500 px-1">
            <span id="recordCount">Showing 0 records</span>
            <span id="selectedCount">0 selected</span>
        </div>

    </div>

    <!-- Back to Dashboard -->
    <div class="max-w-7xl mx-auto px-1">
        <a href="<?php echo BASE_URL; ?>dashboard.php" class="text-sm text-gray-500 hover:text-[#21b799] inline-flex items-center gap-2">&larr; Back to Dashboard</a>
    </div>

    <!-- Watch Demo Modal -->
    <div id="demoModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-lg w-full overflow-hidden transition-all transform scale-100">
            <div class="flex justify-between items-center p-4 border-b border-gray-200 bg-gray-50">
                <h3 class="font-bold text-gray-800 flex items-center gap-2">
                    <i class="fa-solid fa-circle-play text-[#21b799]"></i> Feature Demonstration
                </h3>
                <button onclick="toggleModal(false)" class="text-gray-400 hover:text-gray-600 p-1">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            <div class="p-6 text-center space-y-4">
                <div class="w-16 h-16 bg-emerald-100 text-[#21b799] rounded-full flex items-center justify-center mx-auto text-2xl">
                    <i class="fa-solid fa-play"></i>
                </div>
                <p class="text-sm text-gray-600">This demo video shows how to manage staff directory, filter options by department and designation, and perform bulk operations.</p>
                <div class="pt-2">
                    <button onclick="toggleModal(false)" class="bg-[#21b799] text-white text-sm font-medium px-6 py-2 rounded hover:bg-[#1ca388] transition-colors">
                        Got it, thanks!
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const staffData = <?php echo json_encode($staff); ?>;

        let selectedRows = new Set();

        // Render data into table
        function renderTable(data) {
            const tbody = document.getElementById("staffTableBody");
            tbody.innerHTML = "";

            if (data.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center py-8 text-gray-400 font-medium">
                            No records found matching selected filters.
                        </td>
                    </tr>
                `;
                document.getElementById("recordCount").innerText = "Showing 0 records";
                return;
            }

            data.forEach((row) => {
                const tr = document.createElement("tr");
                tr.className = "hover:bg-gray-50/80 transition-colors border-b border-gray-200";

                const isChecked = selectedRows.has(row.id);

                tr.innerHTML = `
                    <td class="p-3 text-center border-r border-gray-200">
                        <input type="checkbox" ${isChecked ? "checked" : ""} onchange="toggleSelectRow(${row.id}, this)" class="row-checkbox w-4 h-4 accent-emerald-600 rounded cursor-pointer align-middle">
                    </td>
                    <td class="p-3 border-r border-gray-200 font-normal text-gray-800">${escapeHtml(row.name)}</td>
                    <td class="p-3 border-r border-gray-200 text-gray-700">${escapeHtml(row.designation)}</td>
                    <td class="p-3 border-r border-gray-200 text-gray-700">${escapeHtml(row.department)}</td>
                    <td class="p-3 text-gray-700">${escapeHtml(row.contact)}</td>
                `;
                tbody.appendChild(tr);
            });

            document.getElementById("recordCount").innerText = `Showing ${data.length} records`;
            updateSelectAllState(data);
        }

        // Filter functionality
        function filterData() {
            const desigFilter = document.getElementById("designationSelect").value;
            const deptFilter = document.getElementById("departmentSelect").value;

            const filtered = staffData.filter(item => {
                const matchesDesig = desigFilter === "All" || item.designation === desigFilter;
                const matchesDept = deptFilter === "All" || item.department === deptFilter;
                return matchesDesig && matchesDept;
            });

            renderTable(filtered);
        }

        // Selection handlers
        function toggleSelectRow(id, checkbox) {
            if (checkbox.checked) {
                selectedRows.add(id);
            } else {
                selectedRows.delete(id);
            }
            updateSelectedUI();
        }

        function toggleSelectAll(selectAllCheckbox) {
            const currentFilteredData = getCurrentFilteredData();
            if (selectAllCheckbox.checked) {
                currentFilteredData.forEach(row => selectedRows.add(row.id));
            } else {
                currentFilteredData.forEach(row => selectedRows.delete(row.id));
            }
            renderTable(currentFilteredData);
            updateSelectedUI();
        }

        function getCurrentFilteredData() {
            const desigFilter = document.getElementById("designationSelect").value;
            const deptFilter = document.getElementById("departmentSelect").value;
            return staffData.filter(item => {
                const matchesDesig = desigFilter === "All" || item.designation === desigFilter;
                const matchesDept = deptFilter === "All" || item.department === deptFilter;
                return matchesDesig && matchesDept;
            });
        }

        function updateSelectAllState(currentData) {
            const selectAll = document.getElementById("selectAll");
            if (currentData.length === 0) {
                selectAll.checked = false;
                return;
            }
            const allChecked = currentData.every(row => selectedRows.has(row.id));
            selectAll.checked = allChecked;
        }

        function updateSelectedUI() {
            document.getElementById("selectedCount").innerText = `${selectedRows.size} selected`;
        }

        // Modal handler
        function toggleModal(show) {
            const modal = document.getElementById("demoModal");
            if (show) {
                modal.classList.remove("hidden");
            } else {
                modal.classList.add("hidden");
            }
        }

        // Helper to prevent XSS
        function escapeHtml(str) {
            return str.replace(/[&<>"']/g, function(m) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
            });
        }

        window.onload = function() {
            renderTable(staffData);
        };
    </script>
</body>
</html>