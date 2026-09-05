<?php
declare(strict_types=1);
$categorySum = 0.0;
foreach (grading_category_keys() as $key) {
    $categorySum += (float)$weights[$key];
}
$termSum = (float)$weights['midterm'] + (float)$weights['final'];
?>
<div class="bar">
  <h1>Grading system</h1>
</div>
<p class="muted">Set how categories and terms combine into a subject grade. Teachers see these weights on My Subjects. Category weights and the midterm/final blend must each total 100.</p>
<?php errors($gradingErrors); ?>
<section class="card">
  <form method="post" class="grading-weights-form" data-grading-weights>
    <?=csrf_field()?>
    <fieldset class="form-section">
      <legend>Category weights</legend>
      <p class="muted">Applied within each term (midterm and finals).</p>
      <div class="grid">
        <?php foreach (grading_category_keys() as $key): ?>
        <div>
          <label for="weight-<?=e($key)?>"><?=e(grading_category_labels()[$key])?> %</label>
          <input id="weight-<?=e($key)?>" name="<?=e($key)?>" type="number" min="0" max="100" step="0.01" required value="<?=e((string)$weights[$key])?>" data-weight-group="category">
        </div>
        <?php endforeach; ?>
      </div>
      <p class="weight-sum" data-weight-sum="category" data-ok="<?= abs($categorySum - 100) < 0.01 ? '1' : '0' ?>">
        Category total: <strong data-weight-sum-value><?=e(format_grade($categorySum))?></strong>%
        <span data-weight-sum-hint><?= abs($categorySum - 100) < 0.01 ? ' (ready)' : ' — must equal 100' ?></span>
      </p>
    </fieldset>
    <fieldset class="form-section">
      <legend>Term blend</legend>
      <p class="muted">Overall grade after both terms are submitted: Midterm % × midterm grade + Final % × final grade.</p>
      <div class="grid">
        <div>
          <label for="weight-midterm">Midterm %</label>
          <input id="weight-midterm" name="midterm" type="number" min="0" max="100" step="0.01" required value="<?=e((string)$weights['midterm'])?>" data-weight-group="term">
        </div>
        <div>
          <label for="weight-final">Final %</label>
          <input id="weight-final" name="final" type="number" min="0" max="100" step="0.01" required value="<?=e((string)$weights['final'])?>" data-weight-group="term">
        </div>
      </div>
      <p class="weight-sum" data-weight-sum="term" data-ok="<?= abs($termSum - 100) < 0.01 ? '1' : '0' ?>">
        Term blend total: <strong data-weight-sum-value><?=e(format_grade($termSum))?></strong>%
        <span data-weight-sum-hint><?= abs($termSum - 100) < 0.01 ? ' (ready)' : ' — must equal 100' ?></span>
      </p>
    </fieldset>
    <div class="actions">
      <button type="submit">Save weights</button>
    </div>
  </form>
</section>
