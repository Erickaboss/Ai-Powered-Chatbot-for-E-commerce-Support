<?php require_once 'includes/admin_header.php'; ?>
<?php require_once __DIR__ . '/../includes/ml_artifacts.php'; ?>
<?php
$mlDashboard = loadMlArtifacts();
$models = $mlDashboard['models'];
$summary = $mlDashboard['summary'];
$plots = $mlDashboard['plots'];
$reports = $mlDashboard['reports'];
$dataset = $mlDashboard['dataset'];
$vectorizer = $mlDashboard['vectorizer'];
$split = $mlDashboard['split'];
$bestModel = $summary['best_model_row'] ?? [];
$targetAccuracy = (float)($summary['target_accuracy'] ?? 0.85);
$modelsAtTarget = count(array_filter($models, fn(array $model): bool => (float)$model['accuracy'] >= $targetAccuracy));

$formatPercent = function ($value): string {
    if ($value === null || $value === '') {
        return 'N/A';
    }
    return number_format(((float)$value) * 100, 2) . '%';
};

$formatDateTime = function ($value): string {
    if (!$value) {
        return 'N/A';
    }

    $timestamp = strtotime((string)$value);
    return $timestamp ? date('M d, Y H:i', $timestamp) : 'N/A';
};

$metricTone = function ($value) use ($targetAccuracy): string {
    if ($value === null || $value === '') {
        return 'secondary';
    }
    if ((float)$value >= $targetAccuracy) {
        return 'success';
    }
    if ((float)$value >= max(0.70, $targetAccuracy - 0.10)) {
        return 'warning';
    }
    return 'danger';
};
?>
<div class="admin-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="bi bi-graph-up-arrow me-2 text-primary"></i>ML Model Performance Dashboard</h4>
        <small class="text-muted">
            Last trained: <?= htmlspecialchars($formatDateTime($summary['trained_at'] ?? null)) ?>
        </small>
    </div>

    <?php if (!$mlDashboard['available'] || empty($models)): ?>
    <div class="alert alert-warning border-0 shadow-sm">
        <strong>Model artifacts not available yet.</strong>
        Run the chatbot training pipeline to generate `chatbot-ml/models/model_results.json` and the corresponding plots.
    </div>
    <?php else: ?>
    <div class="card p-4 mb-4 text-white" style="background:linear-gradient(135deg,#0f3460,#1f8a70);border:none;">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div>
                <div style="opacity:.8;font-size:.85rem;">Best Performing Model</div>
                <h2 class="mb-2"><?= htmlspecialchars($bestModel['model_name'] ?? ($summary['best_model'] ?? 'N/A')) ?></h2>
                <div style="opacity:.85;font-size:.9rem;">
                    Accuracy target: <strong><?= number_format($targetAccuracy * 100, 0) ?>%</strong>
                    <?php if (!empty($summary['model_version'])): ?>
                    <span class="ms-2 badge bg-light text-dark">v<?= htmlspecialchars((string)$summary['model_version']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="mt-2" style="opacity:.85;font-size:.85rem;">
                    Training samples: <strong><?= number_format((int)($summary['training_samples'] ?? 0)) ?></strong>
                    <?php if (!empty($summary['test_samples'])): ?>
                    | Test samples: <strong><?= number_format((int)$summary['test_samples']) ?></strong>
                    <?php endif; ?>
                    | Intent classes: <strong><?= number_format((int)($summary['num_classes'] ?? 0)) ?></strong>
                </div>
            </div>
            <div class="text-lg-end">
                <div style="font-size:2.7rem;font-weight:900;line-height:1;"><?= $formatPercent($summary['accuracy'] ?? null) ?></div>
                <div style="opacity:.8;font-size:.8rem;">SVM (Linear) accuracy</div>
                <div class="mt-2 badge bg-success">
                    Production Model Deployed
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card p-3 border-start border-4 border-success h-100">
                <div class="small text-muted">SVM Accuracy</div>
                <div class="fs-4 fw-bold text-success"><?= $formatPercent($summary['accuracy'] ?? null) ?></div>
                <div class="small text-muted">Best model performance</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card p-3 border-start border-4 border-primary h-100">
                <div class="small text-muted">Above 85% Target</div>
                <div class="fs-4 fw-bold text-primary"><?= $modelsAtTarget ?>/<?= count($models) ?></div>
                <div class="small text-muted">Professional presentation threshold</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card p-3 border-start border-4 border-warning h-100">
                <div class="small text-muted">Intent Classes</div>
                <div class="fs-4 fw-bold text-warning"><?= number_format((int)($summary['num_classes'] ?? 0)) ?></div>
                <div class="small text-muted">English, French, Kinyarwanda support</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card p-3 border-start border-4 border-info h-100">
                <div class="small text-muted">Training Snapshot</div>
                <div class="fs-4 fw-bold text-info"><?= number_format((int)($summary['training_samples'] ?? 0)) ?></div>
                <div class="small text-muted">Training rows<?= !empty($summary['test_samples']) ? ' + ' . number_format((int)$summary['test_samples']) . ' test rows' : '' ?></div>
            </div>
        </div>
    </div>



    <?php
    // ── Full SVM metrics section (Task 2) ──
    $svmModel = $models[0] ?? [];
    $svmAcc   = isset($svmModel['accuracy'])        ? round((float)$svmModel['accuracy']        * 100, 2) : null;
    $svmTrain = isset($svmModel['train_accuracy'])   ? round((float)$svmModel['train_accuracy']  * 100, 2) : null;
    $svmCvM   = isset($svmModel['cv_mean'])          ? round((float)$svmModel['cv_mean']         * 100, 2) : null;
    $svmCvS   = isset($svmModel['cv_std'])           ? round((float)$svmModel['cv_std']          * 100, 4) : null;
    $svmTrS   = isset($svmModel['training_samples']) ? (int)$svmModel['training_samples']                  : null;
    $svmTeS   = isset($svmModel['test_samples'])     ? (int)$svmModel['test_samples']                      : null;
    $svmVoc   = (int)($summary['vocabulary_size']    ?? $svmModel['vocabulary_size'] ?? 0);
    $svmCls   = (int)($summary['num_classes']        ?? 0);
    ?>

    <!-- Key Stats Cards + Full Metrics Table + Bar Chart -->
    <div class="card p-4 mb-4">
        <h6 class="mb-3"><i class="bi bi-speedometer2 me-2 text-primary"></i>SVM (Linear) — Key Metrics</h6>
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-4 col-lg-2">
                <div class="card text-center p-3 border-start border-4 border-success h-100">
                    <div class="small text-muted">Test Accuracy</div>
                    <div class="fs-4 fw-bold text-success"><?= $svmAcc !== null ? $svmAcc . '%' : 'N/A' ?></div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="card text-center p-3 border-start border-4 border-primary h-100">
                    <div class="small text-muted">Train Accuracy</div>
                    <div class="fs-4 fw-bold text-primary"><?= $svmTrain !== null ? $svmTrain . '%' : 'N/A' ?></div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="card text-center p-3 border-start border-4 border-info h-100">
                    <div class="small text-muted">CV Mean</div>
                    <div class="fs-4 fw-bold text-info"><?= $svmCvM !== null ? $svmCvM . '%' : 'N/A' ?></div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="card text-center p-3 border-start border-4 border-warning h-100">
                    <div class="small text-muted">Vocabulary</div>
                    <div class="fs-4 fw-bold text-warning"><?= $svmVoc ? number_format($svmVoc) : 'N/A' ?></div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="card text-center p-3 border-start border-4 border-secondary h-100">
                    <div class="small text-muted">Training Samples</div>
                    <div class="fs-4 fw-bold text-secondary"><?= $svmTrS ? number_format($svmTrS) : 'N/A' ?></div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="card text-center p-3 border-start border-4 border-danger h-100">
                    <div class="small text-muted">Intent Classes</div>
                    <div class="fs-4 fw-bold text-danger"><?= $svmCls ?: 'N/A' ?></div>
                </div>
            </div>
        </div>

        <h6 class="mb-3"><i class="bi bi-table me-2"></i>Full Metrics Table</h6>
        <div class="table-responsive mb-4">
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Model Name</th>
                        <th>Accuracy</th>
                        <th>Train Accuracy</th>
                        <th>CV Mean</th>
                        <th>CV Std</th>
                        <th>Training Samples</th>
                        <th>Test Samples</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($svmModel)): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($svmModel['model_name']) ?></strong> <span class="badge bg-success ms-1">Active</span></td>
                        <td><span class="badge bg-success fs-6"><?= isset($svmModel['accuracy']) ? round((float)$svmModel['accuracy'] * 100, 2) . '%' : 'N/A' ?></span></td>
                        <td><?= isset($svmModel['train_accuracy']) ? round((float)$svmModel['train_accuracy'] * 100, 2) . '%' : 'N/A' ?></td>
                        <td><?= isset($svmModel['cv_mean']) ? round((float)$svmModel['cv_mean'] * 100, 2) . '%' : 'N/A' ?></td>
                        <td><?= isset($svmModel['cv_std']) ? number_format((float)$svmModel['cv_std'] * 100, 4) . '%' : 'N/A' ?></td>
                        <td><?= isset($svmModel['training_samples']) ? number_format((int)$svmModel['training_samples']) : 'N/A' ?></td>
                        <td><?= isset($svmModel['test_samples']) ? number_format((int)$svmModel['test_samples']) : 'N/A' ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <h6 class="mb-3"><i class="bi bi-bar-chart-fill me-2 text-success"></i>Accuracy / Train Accuracy / CV Mean</h6>
        <div style="height:260px;">
            <canvas id="svmMetricsBar"></canvas>
        </div>
    </div>

    <div class="card p-4 mb-4">
        <h6 class="mb-3"><i class="bi bi-lightbulb me-2 text-warning"></i>Performance Insights</h6>
        <div class="row g-3">
            <div class="col-md-4">
                <div style="padding:16px;background:#e8f5e9;border-radius:12px;border-left:4px solid #2e7d32;">
                    <div class="small text-muted">Best Model</div>
                    <div class="fw-bold fs-5 text-success"><?= htmlspecialchars($bestModel['model_name'] ?? ($summary['best_model'] ?? 'N/A')) ?></div>
                    <div class="small text-muted"><?= $formatPercent($summary['accuracy'] ?? null) ?> accuracy</div>
                </div>
            </div>
            <div class="col-md-4">
                <div style="padding:16px;background:#fff8e1;border-radius:12px;border-left:4px solid #f9a825;">
                    <div class="small text-muted">Model Status</div>
                    <div class="fw-bold fs-5 text-warning">SVM (Linear)</div>
                    <div class="small text-muted">Production model deployed</div>
                </div>
            </div>
            <div class="col-md-4">
                <div style="padding:16px;background:#e3f2fd;border-radius:12px;border-left:4px solid #1976d2;">
                    <div class="small text-muted">Intent Coverage</div>
                    <div class="fw-bold fs-5 text-primary"><?= number_format(count($summary['intents'] ?? [])) ?> intents</div>
                    <div class="small text-muted">Catalog, orders, support, multilingual guidance</div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($dataset) || !empty($reports)): ?>
    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="card p-4 h-100">
                <h6 class="mb-3"><i class="bi bi-diagram-3 me-2 text-secondary"></i>Dataset &amp; Pipeline Snapshot</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="small text-muted">Merged Source Files</div>
                        <div class="fw-semibold"><?= number_format(count($dataset['source_files'] ?? [])) ?></div>
                        <div class="small text-muted">JSON datasets combined before training</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Deduplicated Samples Removed</div>
                        <div class="fw-semibold"><?= number_format((int)($dataset['deduped_samples_removed'] ?? 0)) ?></div>
                        <div class="small text-muted">Overlap removed from merged datasets</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">DB Product Augments</div>
                        <div class="fw-semibold"><?= number_format((int)($dataset['database_augmentation']['product_search_samples'] ?? 0)) ?></div>
                        <div class="small text-muted">Product-name training prompts added</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">FAQ Augments</div>
                        <div class="fw-semibold"><?= number_format((int)($dataset['database_augmentation']['faq_samples'] ?? 0)) ?></div>
                        <div class="small text-muted">FAQ rows converted into training samples</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Vectorizer Vocabulary</div>
                        <div class="fw-semibold"><?= number_format((int)($vectorizer['vocabulary_size'] ?? 0)) ?></div>
                        <div class="small text-muted">TF-IDF ngrams: <?= htmlspecialchars(implode('-', $vectorizer['ngram_range'] ?? [1, 1])) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Split Strategy</div>
                        <div class="fw-semibold"><?= htmlspecialchars(ucfirst((string)($split['strategy'] ?? 'unknown'))) ?></div>
                        <div class="small text-muted">Test size: <?= isset($split['test_size']) ? number_format(((float)$split['test_size']) * 100, 0) . '%' : 'N/A' ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card p-4 h-100">
                <h6 class="mb-3"><i class="bi bi-file-earmark-text me-2 text-info"></i>Saved Reports</h6>
                <?php if (!empty($reports)): ?>
                    <div class="d-grid gap-2">
                        <?php foreach ($reports as $report): ?>
                        <a class="btn btn-outline-secondary text-start" href="<?= htmlspecialchars($report['web_path'] ?? '#') ?>" target="_blank">
                            <div class="fw-semibold"><?= htmlspecialchars($report['label']) ?></div>
                            <div class="small text-muted"><?= htmlspecialchars($report['filename']) ?></div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-muted small">No saved report files were found in the artifact manifest.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($plots)): ?>
    <div class="card p-4 mb-4">
        <h6 class="mb-3"><i class="bi bi-images me-2 text-info"></i>Training Plot Gallery</h6>
        <div class="row g-3">
            <?php foreach ($plots as $plot): ?>
            <div class="col-md-6 col-xl-3">
                <a href="<?= htmlspecialchars($plot['web_path']) ?>" target="_blank" class="text-decoration-none">
                    <div class="border rounded-3 overflow-hidden h-100 bg-light">
                        <img src="<?= htmlspecialchars($plot['web_path']) ?>" alt="<?= htmlspecialchars($plot['label']) ?>" style="width:100%;height:180px;object-fit:cover;" loading="lazy">
                        <div class="p-3">
                            <div class="fw-semibold text-dark small"><?= htmlspecialchars($plot['label']) ?></div>
                            <div class="text-muted small"><?= htmlspecialchars($plot['filename']) ?></div>
                        </div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Chart.js for SVM metrics bar -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
<?php if (!empty($models)): 
    $best = $models[0] ?? [];
    $acc = ($best['accuracy'] ?? 0) * 100;
    $trainAcc = ($best['train_accuracy'] ?? $acc) * 100;
    $cvMean = ($best['cv_mean'] ?? 0) * 100;
?>
new Chart(document.getElementById('svmMetricsBar'), {
    type: 'bar',
    data: {
        labels: ['Test Accuracy', 'Train Accuracy', 'CV Mean'],
        datasets: [{
            label: '<?= htmlspecialchars($best['model_name'] ?? 'SVM (Linear)') ?>',
            data: [<?= $acc ?>, <?= $trainAcc ?>, <?= $cvMean ?>],
            backgroundColor: ['#0d6efd', '#198754', '#ffc107'],
            borderRadius: 6,
            borderSkipped: false
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                ticks: { callback: v => v + '%' }
            }
        }
    }
});
<?php endif; ?>
</script>


