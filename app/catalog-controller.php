<?php
declare(strict_types=1);
require_role(['admin']);
// The router supplies only these two table names.
$catalogTable = $page === 'courses' ? 'courses' : 'departments';
$catalogLabel = $page === 'courses' ? 'course' : 'department';
$catalogErrors = [];
$catalogName = '';
$catalogId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT) ?: null;
$catalogShow = isset($_GET['add']) || $catalogId;
if ($catalogShow || $_SERVER['REQUEST_METHOD']==='POST') require_role(['admin']);
if ($catalogId) {
    $stmt = db()->prepare("SELECT name FROM $catalogTable WHERE id=?");
    $stmt->execute([$catalogId]);
    $catalogName = $stmt->fetchColumn();
    if ($catalogName===false) { http_response_code(404); exit('Record not found.'); }
}
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $catalogName = is_string($_POST['name']??null) ? trim($_POST['name']) : '';
    if ($catalogName==='' || mb_strlen($catalogName)>100) $catalogErrors[]='Enter a name of 1–100 characters.';
    if (!$catalogErrors) {
        db()->beginTransaction();
        try {
            if ($catalogId) db()->prepare("UPDATE $catalogTable SET name=? WHERE id=?")->execute([$catalogName,$catalogId]);
            else { db()->prepare("INSERT INTO $catalogTable (name) VALUES (?)")->execute([$catalogName]); $catalogId=(int)db()->lastInsertId(); }
            // Keep the legacy text column consistent for older report integrations.
            if ($catalogTable==='courses') db()->prepare('UPDATE students SET course=? WHERE course_id=?')->execute([$catalogName,$catalogId]);
            audit('SAVE',$catalogLabel,$catalogId,'Saved '.$catalogLabel);
            db()->commit(); flash('success',ucfirst($catalogLabel).' saved.'); redirect($page);
        } catch (PDOException $exception) {
            db()->rollBack();
            if (($exception->errorInfo[1]??null)===1062) $catalogErrors[]='That name already exists.';
            else { error_log($exception->getMessage()); $catalogErrors[]='Could not save. Please try again.'; }
        }
    }
    $catalogShow=true;
}
$catalogSearch=is_string($_GET['q']??null)?trim($_GET['q']):'';
$like='%'.strtr($catalogSearch,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
$countSql=$page==='courses'?'SELECT COUNT(*) FROM students s WHERE s.course_id=c.id':'SELECT COUNT(*) FROM teachers t JOIN users u ON u.id=t.user_id WHERE t.department_id=c.id AND u.role=\'staff\'';
$stmt=db()->prepare("SELECT c.*, ($countSql) AS assigned_count FROM $catalogTable c WHERE c.name LIKE ? ESCAPE '!' ORDER BY c.name");
$stmt->execute([$like]); $catalogRows=$stmt->fetchAll();
