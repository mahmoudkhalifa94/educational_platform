<?php
// Add these routes to your config/routes.php file

// Bulk grading routes
$router->get('/teacher/assignments/bulk-grade/:id', [new BulkGradeController(), 'index']);
$router->post('/teacher/assignments/bulk-grade/:id', [new BulkGradeController(), 'process']);
$router->post('/teacher/submissions/grade', [new BulkGradeController(), 'gradeSingle']);
// Add these routes to your config/routes.php file

// Student progress routes
$router->get('/teacher/progress', [new ProgressController(), 'index']);
$router->get('/teacher/progress/export', [new ProgressController(), 'export']);

// For individual student progress (could be added to a StudentProfileController)
$router->get('/teacher/student-profile/:id', [new StudentProfileController(), 'index']);
?>