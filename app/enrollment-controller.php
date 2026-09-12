<?php
declare(strict_types=1);
require_once __DIR__ . '/enrollment.php';

$page = $_GET['page'] ?? '';
$enrollErrors = [];
$enrollFieldErrors = [];
$enrollInput = enrollment_empty_input();
$enrollSubmitted = false;
$enrollmentFilters = enrollment_list_filters_from_request();
$enrollmentStatusFilter = $enrollmentFilters['status'];
$enrollmentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: null;
$selectedEnrollment = null;
$selectedDocuments = [];
$reviewErrors = [];
$courseOptions = [];
$applications = [];
$enrollmentTotal = 0;
$enrollmentPages = 1;
$enrollmentPage = 1;
$enrollmentStatusCounts = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'all' => 0];
$enrollmentAcademicYears = [];

if ($page === 'enroll') {
    if (user()) {
        redirect(home_page_for_user());
    }
    try {
        $courseOptions = db()->query('SELECT id, name FROM courses ORDER BY name')->fetchAll();
    } catch (Throwable) {
        $courseOptions = [];
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $rateError = enrollment_rate_limited();
        if ($rateError !== null) {
            $enrollErrors[] = $rateError;
        } else {
            $enrollInput = enrollment_collect_post();
            $validated = enrollment_validate($enrollInput, true);
            $enrollErrors = $validated['messages'];
            $enrollFieldErrors = $validated['fields'];
            if (!$enrollErrors) {
                try {
                    enrollment_submit($enrollInput);
                    enrollment_rate_limit_hit();
                    $enrollSubmitted = true;
                    $enrollInput = enrollment_empty_input();
                    $enrollFieldErrors = [];
                } catch (Throwable $e) {
                    error_log($e->getMessage());
                    $enrollErrors[] = 'Could not save your application. Please try again.';
                }
            }
        }
    }
}

if ($page === 'enrollment_document') {
    require_registrar_capability('enrollments');
    $docId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
    $doc = enrollment_document($docId);
    if (!$doc) {
        deny_request('Document not found.', 404);
    }
    enrollment_serve_document($doc);
}

if ($page === 'enrollments') {
    require_registrar_capability('enrollments');
    try {
        $courseOptions = db()->query('SELECT id, name FROM courses ORDER BY name')->fetchAll();
    } catch (Throwable) {
        $courseOptions = [];
    }
    $enrollmentAcademicYears = enrollment_academic_years();
    $list = enrollment_list_page($enrollmentFilters);
    $applications = $list['rows'];
    $enrollmentTotal = $list['total'];
    $enrollmentPages = $list['pages'];
    $enrollmentPage = $list['page'];
    $enrollmentStatusCounts = $list['status_counts'];
    $enrollmentFilters['page'] = $enrollmentPage;

    if ($enrollmentId) {
        $selectedEnrollment = enrollment_fetch($enrollmentId);
        if (!$selectedEnrollment) {
            http_response_code(404);
            $enrollmentId = null;
        } else {
            $selectedDocuments = enrollment_documents($enrollmentId);
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $enrollmentId) {
        verify_csrf();
        $action = (string)($_POST['review_action'] ?? '');
        $returnFilters = enrollment_list_filters_from_request();
        $returnFilters['status'] = 'pending';
        $returnFilters['page'] = 1;
        try {
            if ($action === 'approve') {
                $studentId = enrollment_approve($enrollmentId, (int)user()['id']);
                $stu = db()->prepare('SELECT username, student_number FROM students WHERE id=?');
                $stu->execute([$studentId]);
                $row = $stu->fetch() ?: [];
                $tempPlain = (string)($_SESSION['last_enrollment_temp_password'] ?? '');
                unset($_SESSION['last_enrollment_temp_password']);
                $isMovingUp = ($selectedEnrollment['application_type'] ?? '') === 'moving_up';
                if ($isMovingUp || $tempPlain === '') {
                    flash('success', 'Enrollee approved. Student ID ' . ($row['student_number'] ?? '') . '; username ' . ($row['username'] ?? '') . '.');
                } else {
                    flash('success', 'Enrollee approved. Student ID ' . ($row['student_number'] ?? '') . '; username ' . ($row['username'] ?? '') . '; temporary password ' . $tempPlain . '. Share it privately — they must change it on first sign-in.');
                }
                redirect('enrollments', array_diff_key(enrollment_list_query_params($returnFilters), ['page' => true]));
            }
            if ($action === 'reject') {
                enrollment_reject($enrollmentId, (int)user()['id'], (string)($_POST['reject_reason'] ?? ''));
                flash('success', 'Application rejected.');
                redirect('enrollments', array_diff_key(enrollment_list_query_params($returnFilters), ['page' => true]));
            }
            $reviewErrors[] = 'Unknown review action.';
        } catch (InvalidArgumentException $e) {
            $reviewErrors[] = $e->getMessage();
            $selectedEnrollment = enrollment_fetch($enrollmentId);
            $selectedDocuments = enrollment_documents($enrollmentId);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            $reviewErrors[] = 'Could not complete the review action.';
            $selectedEnrollment = enrollment_fetch($enrollmentId);
            $selectedDocuments = enrollment_documents($enrollmentId);
        }
    }
}
