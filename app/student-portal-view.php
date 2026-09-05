<?php
declare(strict_types=1);
global $subjects, $selectedSubject, $assignmentId, $subjectLookupError;

$termWeights = grading_weights();
$midtermWeight = format_grade((float)$termWeights['midterm']);
$finalWeight = format_grade((float)$termWeights['final']);

function student_phase_badge_class(string $phase): string
{
    return match ($phase) {
        'complete' => 'badge badge-phase-complete',
        'final' => 'badge badge-phase-final',
        default => 'badge badge-phase-midterm',
    };
}

function student_grade_cell(?float $grade, string $unpublished = '—'): string
{
    if ($grade === null) {
        return '<span class="muted">' . e($unpublished) . '</span>';
    }
    return '<strong>' . e(format_grade($grade)) . '</strong>';
}
?>
<div class="bar"><h1>My Subjects</h1></div>
<p class="muted">Click a subject for teacher details. Grades appear only after your teacher submits midterm or finals. Overall uses the school blend (<?=e($midtermWeight)?>% midterm · <?=e($finalWeight)?>% finals).</p>
<?php if (!empty($subjectLookupError)): ?>
<div class="notice error" role="alert">That subject was not found on your enrollments. Choose a row from the list.</div>
<?php endif; ?>
<div class="table-wrap student-subjects-table">
  <table>
    <thead>
      <tr>
        <th scope="col">Block</th>
        <th scope="col">Subject</th>
        <th scope="col" class="col-teacher">Teacher</th>
        <th scope="col">Status</th>
        <th scope="col">Midterm</th>
        <th scope="col">Finals</th>
        <th scope="col">Overall</th>
      </tr>
    </thead>
    <tbody>
    <?php if (!$subjects): ?>
      <tr><td colspan="7" class="empty-state"><strong>No subjects yet</strong><p>Your registrar or faculty assigns you to blocks and subjects. Check back after enrollment.</p></td></tr>
    <?php else: foreach ($subjects as $row): ?>
      <tr class="row-link" data-href="?page=student_subjects&amp;assignment_id=<?=(int)$row['assignment_id']?>" tabindex="0" aria-label="View details for <?=e($row['subject_code'].' in '.$row['block_name'])?>">
        <td><?=e($row['block_name'])?></td>
        <td><strong><?=e($row['subject_code'])?></strong> · <?=e($row['subject_name'])?></td>
        <td class="col-teacher"><?=e($row['teacher_name'])?></td>
        <td><span class="<?=e(student_phase_badge_class($row['phase']))?>"><?=e(student_publish_status_label($row['phase']))?></span></td>
        <td class="num"><?=student_grade_cell($row['midterm_grade'])?></td>
        <td class="num"><?=student_grade_cell($row['final_grade'])?></td>
        <td class="num"><?=student_grade_cell($row['overall_grade'])?></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php if ($selectedSubject): ?>
<dialog open aria-labelledby="student-grade-title" data-return-url="?page=student_subjects">
  <div class="bar">
    <div>
      <h2 id="student-grade-title" tabindex="-1"><?=e($selectedSubject['subject_code'])?> · <?=e($selectedSubject['subject_name'])?></h2>
      <p class="muted student-grade-meta">
        <span>Block <?=e($selectedSubject['block_name'])?></span>
        <span>Teacher <?=e($selectedSubject['teacher_name'])?></span>
        <span class="<?=e(student_phase_badge_class($selectedSubject['phase']))?>"><?=e(student_publish_status_label($selectedSubject['phase']))?></span>
      </p>
    </div>
    <button type="button" class="icon-button secondary" data-close-dialog aria-label="Close">×</button>
  </div>
  <p class="muted">Unpublished terms stay private. Overall = <?=e($midtermWeight)?>% midterm + <?=e($finalWeight)?>% finals after both terms are submitted.</p>
  <div class="table-wrap">
    <table class="grade-term-table">
      <thead><tr><th scope="col">Term</th><th scope="col">Grade</th></tr></thead>
      <tbody>
        <tr>
          <td>Midterm</td>
          <td><?=student_grade_cell($selectedSubject['midterm_grade'], 'Not published yet')?></td>
        </tr>
        <tr>
          <td>Finals</td>
          <td><?=student_grade_cell($selectedSubject['final_grade'], 'Not published yet')?></td>
        </tr>
        <tr>
          <td>Overall</td>
          <td><?=student_grade_cell($selectedSubject['overall_grade'], 'Available after finals are submitted')?></td>
        </tr>
      </tbody>
    </table>
  </div>
  <div class="actions"><button type="button" class="secondary" data-close-dialog>Close</button></div>
</dialog>
<?php endif; ?>
