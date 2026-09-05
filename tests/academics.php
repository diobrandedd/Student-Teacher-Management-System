<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
$pdo=db();
foreach(['courses','departments','users','teachers','students','audit_logs'] as $table) {
    $ddl=$pdo->query("SHOW CREATE TABLE $table")->fetch(PDO::FETCH_NUM)[1];
    $ddl=preg_replace('/CREATE TABLE/','CREATE TEMPORARY TABLE',$ddl,1);
    $ddl=preg_replace('/,?\n\s*CONSTRAINT[^\n]+/','',$ddl);
    $ddl=preg_replace('/AUTO_INCREMENT=\d+/','AUTO_INCREMENT=1',$ddl);
    $pdo->exec($ddl);
}
$pdo->exec("INSERT INTO courses(id,name) VALUES(1,'Computer Science')");
$pdo->exec("INSERT INTO departments(id,name) VALUES(1,'Computing')");
$pdo->exec("INSERT INTO users(id,full_name,username,email,password_hash,role) VALUES(1,'Test Teacher','testteacher','teacher@example.invalid','unused','staff'),(2,'Test Admin','testadmin','admin@example.invalid','unused','admin')");
sync_teacher(1);
$_SESSION['user']=['id'=>2,'role'=>'admin','full_name'=>'Test Admin'];
$_SESSION['csrf']='test-only-token';
$_SERVER['REQUEST_METHOD']='POST';
$_POST=['csrf'=>'test-only-token'];
$scenario=$argv[1]??'student';
if($scenario==='student' || $scenario==='invalid-course') {
    $_GET=['page'=>'students'];
    $_POST+=['student_number'=>'1234','first_name'=>'Test','last_name'=>'Student','email'=>'student@example.invalid','phone'=>'','address'=>'','course_id'=>$scenario==='student'?'1':'999','year_level'=>'1','academic_status'=>'Active'];
} elseif($scenario==='department') {
    $_GET=['page'=>'teachers','assign'=>'1']; $_POST+=['department_id'=>'1'];
} elseif($scenario==='invalid-department') {
    $_GET=['page'=>'teachers','assign'=>'1']; $_POST+=['department_id'=>'999'];
} else {
    $pdo->exec("INSERT INTO students(student_number,first_name,last_name,email,course,course_id,year_level) VALUES('123','Test','Student','student@example.invalid','Computer Science',1,1)");
    $_GET=['page'=>'courses','edit'=>'1']; $_POST+=['name'=>'Updated Course'];
}
register_shutdown_function(function() use($pdo,$scenario) {
    $error=error_get_last();
    if($error && in_array($error['type'],[E_ERROR,E_PARSE,E_COMPILE_ERROR],true)) { fwrite(STDERR,"FAIL: fatal error\n"); exit(1); }
    $ok=match($scenario) {
        'student' => (($row=$pdo->query('SELECT course_id,course,username FROM students')->fetch()) && (int)$row['course_id']===1 && $row['course']==='Computer Science' && $row['username']==='Student_T'),
        'invalid-course' => (int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn()===0,
        'department' => (int)$pdo->query('SELECT department_id FROM teachers WHERE id=1')->fetchColumn()===1,
        'invalid-department' => $pdo->query('SELECT department_id FROM teachers WHERE id=1')->fetchColumn()===null,
        default => $pdo->query('SELECT course FROM students')->fetchColumn()==='Updated Course' && $pdo->query('SELECT name FROM courses WHERE id=1')->fetchColumn()==='Updated Course',
    };
    while(ob_get_level()) ob_end_clean();
    echo ($ok?'PASS: ':'FAIL: ').$scenario."\n";
    if(!$ok) exit(1);
});
ob_start();
require dirname(__DIR__).'/public/index.php';
