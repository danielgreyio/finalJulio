<?php
// Gantt Chart View
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Check if we have real tasks
$has_real_tasks = false;

// Build query based on user role
if ($user_role === 'admin') {
    // Admins (sales/engineers) can see all tasks with engineer details
    $query = "
        SELECT et.*, q.product_type, u.email as engineer_email, u.id as engineer_id
        FROM engineering_tasks et 
        JOIN quotes q ON et.quote_id = q.id 
        JOIN users u ON et.assigned_to = u.id
        ORDER BY et.start_date ASC
    ";
} else {
    // Regular users can only see their own quotes with engineer details
    $query = "
        SELECT et.*, q.product_type, u.email as engineer_email, u.id as engineer_id
        FROM engineering_tasks et 
        JOIN quotes q ON et.quote_id = q.id 
        JOIN users u ON et.assigned_to = u.id
        WHERE q.user_id = ?
        ORDER BY et.start_date ASC
    ";
}

try {
    $stmt = $pdo->prepare($query);
    if ($user_role === 'admin') {
        $stmt->execute();
    } else {
        $stmt->execute([$user_id]);
    }
    $tasks = $stmt->fetchAll();
    
    // Check if we have real tasks
    $has_real_tasks = !empty($tasks);
    
    // If no tasks exist, create demo tasks for a 5-person engineering team
    if (!$has_real_tasks) {
        $tasks = [
            [
                'id' => '101',
                'title' => 'HVAC System Design',
                'start_date' => date('Y-m-d'),
                'end_date' => date('Y-m-d', strtotime('+10 days')),
                'estimated_hours' => 40,
                'actual_hours' => 35,
                'dependencies' => '',
                'engineer_email' => 'engineer1@ventdepot.com',
                'engineer_id' => 1,
                'status' => 'in_progress',
                'product_type' => 'HVAC System'
            ],
            [
                'id' => '102',
                'title' => 'Ductwork Installation Planning',
                'start_date' => date('Y-m-d', strtotime('+2 days')),
                'end_date' => date('Y-m-d', strtotime('+12 days')),
                'estimated_hours' => 30,
                'actual_hours' => 20,
                'dependencies' => '101',
                'engineer_email' => 'engineer2@ventdepot.com',
                'engineer_id' => 2,
                'status' => 'assigned',
                'product_type' => 'Ductwork'
            ],
            [
                'id' => '103',
                'title' => 'Ventilation Fan Selection',
                'start_date' => date('Y-m-d', strtotime('+5 days')),
                'end_date' => date('Y-m-d', strtotime('+15 days')),
                'estimated_hours' => 25,
                'actual_hours' => 0,
                'dependencies' => '',
                'engineer_email' => 'engineer3@ventdepot.com',
                'engineer_id' => 3,
                'status' => 'new',
                'product_type' => 'Ventilation Equipment'
            ],
            [
                'id' => '104',
                'title' => 'Control System Integration',
                'start_date' => date('Y-m-d', strtotime('+3 days')),
                'end_date' => date('Y-m-d', strtotime('+18 days')),
                'estimated_hours' => 50,
                'actual_hours' => 45,
                'dependencies' => '101,102',
                'engineer_email' => 'engineer4@ventdepot.com',
                'engineer_id' => 4,
                'status' => 'in_progress',
                'product_type' => 'Control Systems'
            ],
            [
                'id' => '105',
                'title' => 'Performance Testing',
                'start_date' => date('Y-m-d', strtotime('+10 days')),
                'end_date' => date('Y-m-d', strtotime('+25 days')),
                'estimated_hours' => 20,
                'actual_hours' => 0,
                'dependencies' => '101,102,103,104',
                'engineer_email' => 'engineer5@ventdepot.com',
                'engineer_id' => 5,
                'status' => 'new',
                'product_type' => 'Testing'
            ],
            [
                'id' => '106',
                'title' => 'Energy Efficiency Optimization',
                'start_date' => date('Y-m-d', strtotime('+8 days')),
                'end_date' => date('Y-m-d', strtotime('+20 days')),
                'estimated_hours' => 35,
                'actual_hours' => 30,
                'dependencies' => '101',
                'engineer_email' => 'engineer1@ventdepot.com',
                'engineer_id' => 1,
                'status' => 'in_progress',
                'product_type' => 'Optimization'
            ],
            [
                'id' => '107',
                'title' => 'Noise Reduction Analysis',
                'start_date' => date('Y-m-d', strtotime('+12 days')),
                'end_date' => date('Y-m-d', strtotime('+22 days')),
                'estimated_hours' => 15,
                'actual_hours' => 0,
                'dependencies' => '104',
                'engineer_email' => 'engineer2@ventdepot.com',
                'engineer_id' => 2,
                'status' => 'new',
                'product_type' => 'Acoustics'
            ]
        ];
    }
} catch (PDOException $e) {
    $error_message = "Error fetching tasks: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gantt Chart - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Frappe Gantt CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/frappe-gantt@0.4.0/dist/frappe-gantt.css">
    <style>
        .gantt-container {
            height: 600px;
            margin-top: 20px;
        }
        .gantt-task-line {
            cursor: pointer;
        }
        .engineer-tag {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-left: 8px;
        }
        .engineer-id-1 { background-color: #dbeafe; color: #1e40af; }
        .engineer-id-2 { background-color: #dcfce7; color: #166534; }
        .engineer-id-3 { background-color: #fef3c7; color: #92400e; }
        .engineer-id-4 { background-color: #fce7f3; color: #9d174d; }
        .engineer-id-5 { background-color: #e0e7ff; color: #4338ca; }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'includes/navigation.php'; ?>
    
    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Project Timeline</h1>
                <p class="text-gray-600 mt-2">View all engineering tasks with assigned engineers</p>
            </div>
            <div class="flex flex-wrap gap-2 mt-4 md:mt-0">
                <a href="engineering-dashboard.php" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 text-sm flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                </a>
                <button onclick="gantt.change_view_mode('Quarter Day')" class="px-3 py-1 bg-gray-100 text-gray-700 rounded-md text-sm hover:bg-gray-200">
                    Quarter Day
                </button>
                <button onclick="gantt.change_view_mode('Half Day')" class="px-3 py-1 bg-gray-100 text-gray-700 rounded-md text-sm hover:bg-gray-200">
                    Half Day
                </button>
                <button onclick="gantt.change_view_mode('Day')" class="px-3 py-1 bg-gray-100 text-gray-700 rounded-md text-sm hover:bg-gray-200">
                    Day
                </button>
                <button onclick="gantt.change_view_mode('Week')" class="px-3 py-1 bg-gray-100 text-gray-700 rounded-md text-sm hover:bg-gray-200">
                    Week
                </button>
                <button onclick="gantt.change_view_mode('Month')" class="px-3 py-1 bg-blue-600 text-white rounded-md text-sm hover:bg-blue-700">
                    Month
                </button>
            </div>
        </div>
        
        <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <p><?php echo htmlspecialchars($error_message); ?></p>
            </div>
        <?php endif; ?>
        
        <!-- Engineer Legend -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Engineering Team</h2>
            <div class="flex flex-wrap gap-2">
                <?php 
                // Get unique engineers
                $engineers = [];
                foreach ($tasks as $task) {
                    if (!isset($engineers[$task['engineer_id']])) {
                        $engineers[$task['engineer_id']] = $task['engineer_email'];
                    }
                }
                
                // If we're using demo tasks, ensure we show all 5 engineers
                if (!$has_real_tasks && count($engineers) < 5) {
                    $engineers = [
                        1 => 'engineer1@ventdepot.com',
                        2 => 'engineer2@ventdepot.com',
                        3 => 'engineer3@ventdepot.com',
                        4 => 'engineer4@ventdepot.com',
                        5 => 'engineer5@ventdepot.com'
                    ];
                }
                
                foreach ($engineers as $id => $email): ?>
                    <span class="engineer-tag engineer-id-<?php echo ($id % 5) + 1; ?>">
                        <i class="fas fa-user mr-1"></i><?php echo htmlspecialchars($email); ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Gantt Chart Container -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-semibold text-gray-800">Engineering Tasks Timeline</h2>
                <div class="flex space-x-2">
                    <button onclick="exportGantt()" class="px-3 py-1 bg-gray-100 text-gray-700 rounded-md text-sm hover:bg-gray-200">
                        <i class="fas fa-download mr-1"></i>Export
                    </button>
                </div>
            </div>
            
            <div id="gantt-container" class="gantt-container"></div>
        </div>
        
        <!-- Task Details Panel -->
        <div id="taskDetailsPanel" class="fixed inset-y-0 right-0 w-96 bg-white shadow-lg transform translate-x-full transition-transform duration-300 z-50">
            <div class="h-full overflow-y-auto">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-900">Task Details</h3>
                        <button onclick="closeTaskDetails()" class="text-gray-400 hover:text-gray-500">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                
                <div class="p-6" id="taskDetailsContent">
                    <!-- Task details will be loaded here -->
                </div>
            </div>
        </div>
    </div>
    
    <!-- Frappe Gantt JS -->
    <script src="https://cdn.jsdelivr.net/npm/frappe-gantt@0.4.0/dist/frappe-gantt.min.js"></script>
    
    <script>
        // Prepare tasks data for Gantt chart
        const tasks = [
            <?php foreach ($tasks as $task): ?>
            {
                id: '<?php echo $task['id']; ?>',
                name: 'Task #<?php echo $task['id']; ?> (<?php 
                    $duration = ceil((strtotime($task['end_date'] ? $task['end_date'] : date('Y-m-d', strtotime('+1 week'))) - strtotime($task['start_date'] ? $task['start_date'] : date('Y-m-d'))) / (60 * 60 * 24));
                    echo $duration;
                ?> days)',
                start: '<?php echo $task['start_date'] ? $task['start_date'] : date('Y-m-d'); ?>',
                end: '<?php echo $task['end_date'] ? $task['end_date'] : date('Y-m-d', strtotime('+1 week')); ?>',
                progress: <?php 
                    $progress = 0;
                    if ($task['estimated_hours'] > 0) {
                        $progress = min(100, ($task['actual_hours'] / $task['estimated_hours']) * 100);
                    }
                    echo $progress;
                ?>,
                dependencies: '<?php echo $task['dependencies'] ?? ''; ?>',
                engineer_id: '<?php echo $task['engineer_id']; ?>',
                engineer_email: '<?php echo htmlspecialchars($task['engineer_email']); ?>',
                custom_class: '<?php 
                    switch ($task['status']) {
                        case 'new': echo 'new-task'; break;
                        case 'assigned': echo 'assigned-task'; break;
                        case 'in_progress': echo 'in-progress-task'; break;
                        case 'review': echo 'review-task'; break;
                        case 'completed': echo 'completed-task'; break;
                        default: echo '';
                    }
                ?>'
            },
            <?php endforeach; ?>
        ];
        
        // Initialize Gantt chart
        const gantt = new Gantt("#gantt-container", tasks, {
            header_height: 50,
            column_width: 30,
            step: 24,
            view_modes: ['Quarter Day', 'Half Day', 'Day', 'Week', 'Month'],
            bar_height: 20,
            bar_corner_radius: 3,
            arrow_curve: 5,
            padding: 18,
            view_mode: 'Week',
            date_format: 'YYYY-MM-DD',
            custom_popup_html: function(task) {
                // Ultra-simple popup showing only task ID and duration
                return `
                    <div class="p-2 bg-white rounded shadow-lg border border-gray-200 text-sm">
                        <div class="font-bold">Task #${task.id}</div>
                        <div class="text-gray-600">${task._start.format('MMM D')} - ${task._end.format('MMM D')}</div>
                        <div class="text-blue-600 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>Click for details
                        </div>
                    </div>
                `;
            }
        });
        
        // Add click event to task bars
        document.querySelectorAll('.gantt-task-line').forEach(bar => {
            bar.addEventListener('click', function() {
                const taskId = this.getAttribute('data-id');
                showTaskDetails(taskId);
            });
        });
        
        function showTaskDetails(taskId) {
            // Find the task
            const task = tasks.find(t => t.id == taskId);
            if (task) {
                // Format dates
                const startDate = new Date(task.start).toLocaleDateString();
                const endDate = new Date(task.end).toLocaleDateString();
                
                // Engineer tag
                const engineerTagClass = `engineer-tag engineer-id-${(task.engineer_id % 5) + 1}`;
                
                // Calculate duration
                const start = new Date(task.start);
                const end = new Date(task.end);
                const durationDays = Math.ceil((end - start) / (1000 * 60 * 60 * 24));
                
                const esc = s => String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
                document.getElementById('taskDetailsContent').innerHTML = `
                    <div class="space-y-4">
                        <div>
                            <h4 class="text-lg font-semibold text-gray-900">${esc(task.name)}</h4>
                            <p class="text-gray-600 mt-1">Engineering Task #${esc(task.id)}</p>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex justify-between mb-2">
                                <span class="text-sm text-gray-600">Progress</span>
                                <span class="text-sm font-medium">${Math.round(task.progress)}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: ${task.progress}%"></div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-600">Start Date</p>
                                <p class="font-medium">${esc(startDate)}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">End Date</p>
                                <p class="font-medium">${esc(endDate)}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Duration</p>
                                <p class="font-medium">${esc(durationDays)} days</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Status</p>
                                <p class="font-medium capitalize">${esc(task.custom_class.replace('-task', '').replace('-', ' '))}</p>
                            </div>
                        </div>

                        <div>
                            <p class="text-sm text-gray-600">Assigned Engineer</p>
                            <p class="font-medium"><span class="${esc(engineerTagClass)}"><i class="fas fa-user mr-1"></i>${esc(task.engineer_email)}</span></p>
                            <p class="text-sm text-gray-500 mt-1">Engineer ID: ${esc(task.engineer_id)}</p>
                        </div>
                        
                        <div>
                            <p class="text-sm text-gray-600">Dependencies</p>
                            <p class="font-medium">${task.dependencies || 'None'}</p>
                        </div>
                        
                        <div class="pt-4">
                            <button class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                <i class="fas fa-edit mr-2"></i>Edit Task
                            </button>
                        </div>
                    </div>
                `;
                
                // Show the panel
                document.getElementById('taskDetailsPanel').classList.remove('translate-x-full');
            }
        }
        
        function closeTaskDetails() {
            document.getElementById('taskDetailsPanel').classList.add('translate-x-full');
        }
        
        function exportGantt() {
            // In a real implementation, you would export the Gantt chart
            alert('Export functionality would be implemented here');
        }
        
        // Close panel when clicking outside
        document.addEventListener('click', function(event) {
            const panel = document.getElementById('taskDetailsPanel');
            const isClickInsidePanel = panel.contains(event.target);
            const isClickOnTask = event.target.closest('.gantt-task-line');
            
            if (!isClickInsidePanel && !isClickOnTask && !panel.classList.contains('translate-x-full')) {
                closeTaskDetails();
            }
        });
    </script>
</body>
</html>