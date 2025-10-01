<?php
session_start();

// إعداد قاعدة البيانات
$db_file = __DIR__ . '/tasks.db';
try {
    $db = new PDO("sqlite:$db_file");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // إنشاء الجدول
    $db->exec("CREATE TABLE IF NOT EXISTS tasks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        description TEXT,
        status TEXT DEFAULT 'pending',
        priority TEXT DEFAULT 'medium',
        due_date TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch(PDOException $e) {
    die("خطأ في قاعدة البيانات: " . $e->getMessage());
}

// معالجة النماذج
if ($_POST['action'] == 'add_task') {
    $title = trim($_POST['title']);
    if (!empty($title)) {
        $stmt = $db->prepare("INSERT INTO tasks (title, description, priority, due_date) 
                             VALUES (?, ?, ?, ?)");
        $stmt->execute([$title, $_POST['description'], $_POST['priority'], $_POST['due_date']]);
        $_SESSION['message'] = "تمت الإضافة بنجاح!";
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if (isset($_GET['delete'])) {
    $stmt = $db->prepare("DELETE FROM tasks WHERE id = ?");
    $stmt->execute([intval($_GET['delete'])]);
    $_SESSION['message'] = "تم الحذف بنجاح!";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if (isset($_GET['update_status'])) {
    $stmt = $db->prepare("UPDATE tasks SET status = ? WHERE id = ?");
    $stmt->execute([$_GET['status'], intval($_GET['update_status'])]);
    $_SESSION['message'] = "تم تحديث الحالة بنجاح!";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// جلب المهام مع التصحيح
$filter = $_GET['filter'] ?? 'all';

// استخدام prepared statements للتصفية
if ($filter != 'all') {
    $stmt = $db->prepare("SELECT * FROM tasks WHERE status = ? ORDER BY created_at DESC");
    $stmt->execute([$filter]);
    $tasks = $stmt;
} else {
    $tasks = $db->query("SELECT * FROM tasks ORDER BY created_at DESC");
}

// جلب الإحصائيات مع التعامل مع الأخطاء
try {
    $total_stmt = $db->query("SELECT COUNT(*) FROM tasks");
    $total = $total_stmt ? $total_stmt->fetchColumn() : 0;
    
    $completed_stmt = $db->prepare("SELECT COUNT(*) FROM tasks WHERE status = ?");
    $completed_stmt->execute(['completed']);
    $completed = $completed_stmt->fetchColumn();
    
    $pending_stmt = $db->prepare("SELECT COUNT(*) FROM tasks WHERE status = ?");
    $pending_stmt->execute(['pending']);
    $pending = $pending_stmt->fetchColumn();
    
    $in_progress_stmt = $db->prepare("SELECT COUNT(*) FROM tasks WHERE status = ?");
    $in_progress_stmt->execute(['in_progress']);
    $in_progress = $in_progress_stmt->fetchColumn();
    
} catch(PDOException $e) {
    // في حالة وجود خطأ، ضع قيم افتراضية
    $total = 0;
    $completed = 0;
    $pending = 0;
    $in_progress = 0;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎯 مدير المهام</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #f5f5f5;
            --primary-dark: #f0f0f0;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #1f2937;
            --light: #f8fafc;
            --gray: #6b7280;
            --gray-light: #e5e7eb;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f0f0f0 0%, #f5f5f5 100%);
            color: var(--dark);
            line-height: 1.6;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .glass-card {
            background: #f1f1f1;
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            color: var(--dark);
            font-size: 2.5rem;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #444, #f0f0f0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: inline-block;
        }

        .header p {
            color: #444);
            font-size: 1.1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: #f1f1f1;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border-left: 4px solid var(--primary);
            transition: transform 0.3s ease;
 color :#444;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card.completed {
            border-left-color: var(--success);
        }

        .stat-card.pending {
            border-left-color: var(--warning);
        }

        .stat-card.in-progress {
            border-left-color: #ddcc77;
        }

        .stat-card.total {
            border-left-color: #8b5cf6;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--dark);
            display: block;
        }

        .stat-label {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .task-form {
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        input, textarea, select {
            width: 100%;
            padding: 15px;
            border: 2px solid var(--gray-light);
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }

        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #444;
            transform: translateY(2px);
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.4);
        }

        .filters {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .filter-btn {
            padding: 12px 24px;
            background: white;
            border: 2px solid var(--gray-light);
            border-radius: 25px;
            color: var(--gray);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .filter-btn.active,
        .filter-btn:hover {
            background: var(--gray-light);
            color: #444;
            border-color: var(--gray-light);
        }

        .tasks-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .task-card {
            background: #f5f5f5;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border-left: 5px solid var(--primary);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .task-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .task-card.priority-high {
            border-left-color: var(--danger);
        }

        .task-card.priority-medium {
            border-left-color: var(--warning);
        }

        .task-card.priority-low {
            border-left-color: var(--success);
        }

        .task-card.completed {
            opacity: 0.8;
            background: #f8fafc;
        }

        .task-card.completed::before {
            content: "✓";
            position: absolute;
            top: 10px;
            left: 10px;
            background: var(--success);
            color: #444;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .task-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 8px;
        }

        .task-description {
            color: var(--gray);
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .task-meta {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }

        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .badge.priority-high {
            background: #fee2e2;
            color: var(--danger);
        }

        .badge.priority-medium {
            background: #fef3c7;
            color: var(--warning);
        }

        .badge.priority-low {
            background: #d1fae5;
            color: var(--success);
        }

        .badge.status-pending {
            background: #f3f4f6;
            color: var(--gray);
        }

        .badge.status-completed {
            background: #d1fae5;
            color: var(--success);
        }

        .badge.status-in_progress {
            background: #dbeafe;
            color: var(--primary);
        }

        .task-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .status-select {
            padding: 8px 12px;
            border: 1px solid var(--gray-light);
            border-radius: 8px;
            background: white;
            cursor: pointer;
        }

        .btn-icon {
            padding: 8px 12px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9rem;
            text-decoration: none;
        }

        .btn-delete {
            background: #fee2e2;
            color: var(--danger);
        }

        .btn-delete:hover {
            background: var(--danger);
            color: white;
        }

        .message {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 500;
            text-align: center;
        }

        .message.success {
            background: #d1fae5;
            color: var(--success);
            border: 1px solid #a7f3d0;
        }

        .footer {
            text-align: center;
            margin-top: 40px;
            color: #444;
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .filters {
                justify-content: center;
            }
            
            .task-header {
                flex-direction: column;
                gap: 15px;
            }
            
            .task-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* تأثيرات إضافية */
        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .error {
            background: #fee2e2;
            color: var(--danger);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #fecaca;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- الرسائل -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="message success fade-in">
                ✅ <?= $_SESSION['message'] ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <!-- الهيدر -->
        <div class="glass-card header">
            <h1>🎯 مدير المهام</h1>
            <p>نظم مهامك بكل سهولة وأناقة</p>
        </div>

        <!-- الإحصائيات -->
        <div class="stats-grid">
            <div class="stat-card total pulse">
                <span class="stat-number"><?= $total ?></span>
                <span class="stat-label">المجموع</span>
            </div>
            <div class="stat-card completed">
                <span class="stat-number"><?= $completed ?></span>
                <span class="stat-label">مكتملة</span>
            </div>
            <div class="stat-card pending">
                <span class="stat-number"><?= $pending ?></span>
                <span class="stat-label">قيد الانتظار</span>
            </div>
            <div class="stat-card in-progress text-primary">
                <span class="stat-number"><?= $in_progress ?></span>
                <span class="stat-label">قيد التنفيذ</span>
            </div>
        </div>

        <!-- نموذج إضافة المهمة -->
        <div class="glass-card task-form fade-in">
            <h2 style="margin-bottom: 20px; color: var(--dark);">➕ إضافة مهمة جديدة</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_task">
                <div class="form-group">
                    <input type="text" name="title" placeholder="✏️ عنوان المهمة" required>
                </div>
                <div class="form-group">
                    <textarea name="description" placeholder="📝 وصف المهمة (اختياري)"></textarea>
                </div>
                <div class="form-row">
                    <select name="priority">
                        <option value="low">⬇️ أولوية منخفضة</option>
                        <option value="medium" selected>🔄 أولوية متوسطة</option>
                        <option value="high">⬆️ أولوية عالية</option>
                    </select>
                    <input type="date" name="due_date">
                </div>
                <button type="submit" class="btn btn-primary">
                    ➕ إضافة المهمة
                </button>
            </form>
        </div>

        <!-- الفلاتر -->
        <div class="filters">
            <a href="?filter=all" class="filter-btn <?= $filter == 'all' ? 'active' : '' ?>">📋 الكل</a>
            <a href="?filter=pending" class="filter-btn <?= $filter == 'pending' ? 'active' : '' ?>">⏳ قيد الانتظار</a>
            <a href="?filter=in_progress" class="filter-btn <?= $filter == 'in_progress' ? 'active' : '' ?>">🚀 قيد التنفيذ</a>
            <a href="?filter=completed" class="filter-btn <?= $filter == 'completed' ? 'active' : '' ?>">✅ مكتملة</a>
        </div>

        <!-- قائمة المهام -->
        <div class="tasks-list">
            <?php 
            $has_tasks = false;
            if ($tasks) {
                while ($task = $tasks->fetch(PDO::FETCH_ASSOC)): 
                    $has_tasks = true;
            ?>
                <div class="task-card priority-<?= $task['priority'] ?> <?= $task['status'] == 'completed' ? 'completed' : '' ?> fade-in">
                    <div class="task-header">
                        <div style="flex: 1;">
                            <div class="task-title"><?= htmlspecialchars($task['title']) ?></div>
                            <?php if ($task['description']): ?>
                                <div class="task-description"><?= htmlspecialchars($task['description']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="task-meta">
                        <span class="badge priority-<?= $task['priority'] ?>">
                            <?php
                            $priority_icons = [
                                'low' => '⬇️',
                                'medium' => '🔄', 
                                'high' => '⬆️'
                            ];
                            $priority_text = [
                                'low' => 'منخفض',
                                'medium' => 'متوسط',
                                'high' => 'عالي'
                            ];
                            echo $priority_icons[$task['priority']] . ' ' . $priority_text[$task['priority']];
                            ?>
                        </span>
                        
                        <span class="badge status-<?= $task['status'] ?>">
                            <?php
                            $status_icons = [
                                'pending' => '⏳',
                                'in_progress' => '🚀',
                                'completed' => '✅'
                            ];
                            $status_text = [
                                'pending' => 'قيد الانتظار',
                                'in_progress' => 'قيد التنفيذ',
                                'completed' => 'مكتملة'
                            ];
                            echo $status_icons[$task['status']] . ' ' . $status_text[$task['status']];
                            ?>
                        </span>
                        
                        <?php if (!empty($task['due_date'])): ?>
                            <span class="badge" style="background: #dbeafe; color: #1d4ed8;">
                                📅 <?= $task['due_date'] ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="task-actions">
                        <select class="status-select" onchange="location.href='?update_status=<?= $task['id'] ?>&status='+this.value">
                            <option value="pending" <?= $task['status'] == 'pending' ? 'selected' : '' ?>>⏳ قيد الانتظار</option>
                            <option value="in_progress" <?= $task['status'] == 'in_progress' ? 'selected' : '' ?>>🚀 قيد التنفيذ</option>
                            <option value="completed" <?= $task['status'] == 'completed' ? 'selected' : '' ?>>✅ مكتملة</option>
                        </select>
                        
                        <a href="?delete=<?= $task['id'] ?>" 
                           class="btn-icon btn-delete" 
                           onclick="return confirm('🗑️ هل أنت متأكد من حذف هذه المهمة؟')">
                            🗑️ حذف
                        </a>
                    </div>
                </div>
            <?php 
                endwhile;
            }
            ?>

            <?php if (!$has_tasks): ?>
                <div class="glass-card empty-state fade-in">
                    <div>📝</div>
                    <h3 style="margin-bottom: 10px; color: var(--gray);">لا توجد مهام</h3>
                    <p style="color: var(--gray);">ابدأ بإضافة مهمة جديدة باستخدام النموذج أعلاه</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- الفوتر -->
        <div class="footer">
            نظمو اوقاتكم تفلحو | مع تحياتي hozaifa01
        </div>
    </div>

    <script>
        // تأثيرات بسيطة
        document.addEventListener('DOMContentLoaded', function() {
            // إضافة تأثير عند تحميل الصفحة
            const cards = document.querySelectorAll('.fade-in');
            cards.forEach((card, index) => {
                card.style.animationDelay = (index * 0.1) + 's';
            });

            // تأكيد الحذف
            const deleteButtons = document.querySelectorAll('.btn-delete');
            deleteButtons.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    if (!confirm('🗑️ هل أنت متأكد من حذف هذه المهمة؟')) {
                        e.preventDefault();
                    }
                });
            });
            
            // إضافة تاريخ اليوم كقيمة افتراضية
            const dueDateInput = document.querySelector('input[name="due_date"]');
            if (dueDateInput && !dueDateInput.value) {
                const today = new Date().toISOString().split('T')[0];
                dueDateInput.value = today;
            }
        });
    </script>
</body>
</html>
