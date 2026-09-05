<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/blocks.php';
$pdo = db();
// Connection-local tables shadow the real tables; no existing rows are touched.
foreach (['users', 'teachers', 'students', 'blocks', 'block_students', 'block_subject_assignments', 'block_subject_enrollments', 'audit_logs', 'courses', 'departments'] as $table) {
    $ddl = $pdo->query("SHOW CREATE TABLE $table")->fetch(PDO::FETCH_NUM)[1];
    $ddl = preg_replace('/CREATE TABLE/', 'CREATE TEMPORARY TABLE', $ddl, 1);
    $ddl = preg_replace('/,?\n\s*CONSTRAINT[^\n]+/', '', $ddl);
    $ddl = preg_replace('/AUTO_INCREMENT=\d+/', 'AUTO_INCREMENT=1', $ddl);
    $pdo->exec($ddl);
}
$pdo->exec("INSERT INTO users(id,full_name,username,email,password_hash,role) VALUES(1,'Test Teacher','test-teacher','teacher@example.invalid','unused','staff'),(2,'Test Admin','test-admin','admin@example.invalid','unused','admin'),(3,'Second Teacher','second-teacher','second@example.invalid','unused','staff')");
sync_teacher(1);
sync_teacher(1);
sync_teacher(2);
sync_teacher(3);
$pdo->exec("INSERT INTO students(id,student_number,first_name,last_name,email,course,year_level) VALUES(1,'100','Test','One','one@example.invalid','Test',1),(2,'200','Test','Two','two@example.invalid','Test',1)");
function check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function rejected(callable $action, string $message): void {
    try { $action(); } catch (InvalidArgumentException $e) { return; }
    throw new RuntimeException($message);
}
check((int)$pdo->query('SELECT COUNT(*) FROM teachers')->fetchColumn()===2, 'Teacher sync must be idempotent and staff-only.');
$a = save_block($pdo, ['name'=>'Block A','student_ids'=>[1,2,2]]);
$b = save_block($pdo, ['name'=>'Block B','student_ids'=>[1]]);
check((int)$pdo->query('SELECT COUNT(*) FROM block_students WHERE student_id=1')->fetchColumn()===2, 'Student must be assignable to multiple blocks.');
check((int)$pdo->query("SELECT COUNT(*) FROM block_students WHERE block_id=$a")->fetchColumn()===2, 'Duplicate selections must not duplicate membership.');
save_block($pdo, ['name'=>'Block A updated','student_ids'=>[2]], $a);
check((int)$pdo->query("SELECT COUNT(*) FROM block_students WHERE student_id=1 AND block_id=$b")->fetchColumn()===1, 'Editing a block must preserve other memberships.');
rejected(fn()=>save_block($pdo, ['name'=>'Block B','student_ids'=>[1]], $a), 'Duplicate block name should fail.');
check($pdo->query("SELECT name FROM blocks WHERE id=$a")->fetchColumn()==='Block A updated', 'Failed edit must roll back.');
rejected(fn()=>save_block($pdo, ['name'=>'Invalid','student_ids'=>[999]]), 'Unknown student must fail.');
rejected(fn()=>save_block($pdo, ['name'=>'Invalid','student_ids'=>[]]), 'Empty membership must fail.');
rejected(fn()=>save_block($pdo, ['name'=>'Invalid','student_ids'=>[[1]]]), 'Malformed membership must fail.');
$pdo->exec('UPDATE students SET is_active=0 WHERE id=1');
rejected(fn()=>save_block($pdo, ['name'=>'Invalid','student_ids'=>[1]]), 'Inactive student must fail.');
check((int)$pdo->query('SELECT COUNT(*) FROM blocks')->fetchColumn()===2, 'Rejected requests must not leave blocks.');

$teacherOne = (int)$pdo->query('SELECT id FROM teachers WHERE user_id=1')->fetchColumn();
$teacherTwo = (int)$pdo->query('SELECT id FROM teachers WHERE user_id=3')->fetchColumn();
save_block($pdo, ['name'=>'Block B','student_ids'=>[1]], $b);
$assignMath = save_block_assignment($pdo, $b, ['teacher_id'=>$teacherOne,'subject_code'=>'MATH101','subject_name'=>'College Algebra']);
check((int)$pdo->query("SELECT COUNT(*) FROM block_subject_enrollments WHERE assignment_id=$assignMath")->fetchColumn()===1, 'Assignment must snapshot current members.');
save_block($pdo, ['name'=>'Block B','student_ids'=>[1,2]], $b);
check((int)$pdo->query("SELECT COUNT(*) FROM block_subject_enrollments WHERE assignment_id=$assignMath")->fetchColumn()===1, 'Roster edits must not auto-sync enrollments.');
$synced = resync_block_assignment($pdo, $b, $assignMath);
check($synced===2 && (int)$pdo->query("SELECT COUNT(*) FROM block_subject_enrollments WHERE assignment_id=$assignMath")->fetchColumn()===2, 'Re-sync must refresh enrollments from current members.');
$assignEng = save_block_assignment($pdo, $b, ['teacher_id'=>$teacherTwo,'subject_code'=>'ENG101','subject_name'=>'Communication']);
check((int)$pdo->query("SELECT COUNT(*) FROM block_subject_assignments WHERE block_id=$b")->fetchColumn()===2, 'A block may have multiple teacher-subject assignments.');
rejected(fn()=>save_block_assignment($pdo, $b, ['teacher_id'=>$teacherOne,'subject_code'=>'MATH101','subject_name'=>'Repeat']), 'Duplicate subject code in a block must fail.');
rejected(fn()=>save_block_assignment($pdo, $b, ['teacher_id'=>999,'subject_code'=>'SCI101','subject_name'=>'Science']), 'Unknown teacher must fail.');
$pdo->exec('UPDATE users SET is_active=0 WHERE id=1');
rejected(fn()=>save_block_assignment($pdo, $b, ['teacher_id'=>$teacherOne,'subject_code'=>'SCI101','subject_name'=>'Science']), 'Inactive teacher must fail.');
$pdo->exec('UPDATE users SET is_active=1 WHERE id=1');
delete_block_assignment($pdo, $b, $assignEng);
check((int)$pdo->query("SELECT COUNT(*) FROM block_subject_assignments WHERE id=$assignEng")->fetchColumn()===0, 'Deleting an assignment must remove it.');
check((int)$pdo->query("SELECT COUNT(*) FROM block_subject_enrollments WHERE assignment_id=$assignEng")->fetchColumn()===0, 'Deleting an assignment must cascade enrollments.');

$_SESSION['user'] = ['id'=>2, 'role'=>'admin'];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['q'=>'Test Teacher'];
require dirname(__DIR__) . '/app/blocks-controller.php';
check($blockTotal===1, 'Search by teacher must find blocks with that teacher assignment.');
$_GET = ['q'=>'MATH101'];
require dirname(__DIR__) . '/app/blocks-controller.php';
check($blockTotal===1, 'Search by subject code must find the block.');
$_GET = ['q'=>'Block A updated'];
require dirname(__DIR__) . '/app/blocks-controller.php';
check($blockTotal===1, 'Search by block name must narrow results.');
$_GET = ['q'=>'%'];
require dirname(__DIR__) . '/app/blocks-controller.php';
check($blockTotal===0, 'Search wildcards must be treated literally.');
$_GET = ['edit'=>$b];
require dirname(__DIR__) . '/app/blocks-controller.php';
check(in_array(1, $selectedStudents, true), 'Edit must load existing membership.');
check(count(array_filter($blockStudents, fn($s)=>(int)$s['id']===1))===1, 'Existing inactive members must remain visible.');
$_GET = ['q'=>'Test Teacher'];
require dirname(__DIR__) . '/app/teachers-controller.php';
check($teacherTotal===1 && (int)$teacherRows[0]['block_count']===1, 'Teacher directory must count distinct assigned blocks.');
$_GET = ['q'=>'teacher@example.invalid'];
require dirname(__DIR__) . '/app/teachers-controller.php';
check($teacherTotal===1, 'Teacher directory must search email.');
$_GET = ['q'=>'%'];
require dirname(__DIR__) . '/app/teachers-controller.php';
check($teacherTotal===0, 'Teacher search must escape wildcards.');
$_GET = ['teacher_id'=>$teacherOne];
require dirname(__DIR__) . '/app/blocks-controller.php';
check($blockTotal===1, 'Teacher block link must filter by exact teacher ID via assignments.');
$_GET = ['teacher_id'=>999];
require dirname(__DIR__) . '/app/blocks-controller.php';
check($blockTotal===0, 'Unknown teacher filter must not show other teachers blocks.');
echo "PASS: blocks, multi-subject assignments, snapshot sync, re-sync, search, and teacher block counts.\n";
