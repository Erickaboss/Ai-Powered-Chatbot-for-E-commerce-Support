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
$bestModel = $summary['best_model_row'];
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
                <div style="opacity:.8;font-size:.8rem;">Best accuracy</div>
                <div class="mt-2 badge bg-<?= !empty($summary['all_models_above_target']) ? 'success' : 'warning' ?>">
                    <?= !empty($summary['all_models_above_target']) ? 'All models above target' : 'Some models below target' ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card p-3 border-start border-4 border-success h-100">
                <div class="small text-muted">Average Accuracy</div>
                <div class="fs-4 fw-bold text-success"><?= $formatPercent($summary['average_accuracy'] ?? null) ?></div>
                <div class="small text-muted">Across <?= count($models) ?> trained models</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card p-3 border-start border-4 border-primary h-100">
                <div class="small text-muted">Models Above 85%</div>
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

    <div class="card p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0"><i class="bi bi-table me-2"></i>Model Performance Metrics</h6>
            <span class="badge bg-dark">Artifact-backed</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Model</th>
                        <th>Accuracy</th>
                        <th>Precision</th>
                        <th>Recall</th>
                        <th>F1 Score</th>
                        <th>CV Mean</th>
                        <th>CV Std</th>
                        <th>Train/Test</th>
                        <th>Version</th>
                        <th>Trained On</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($models as $model): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($model['model_name']) ?></strong>
                            <?php if (!empty($bestModel) && $model['model_name'] === $bestModel['model_name']): ?>
                            <span class="badge bg-success ms-1">Best</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="progress mb-1" style="height:6px;width:120px;">
                                <div class="progress-bar bg-<?= $metricTone($model['accuracy']) ?>" style="width:<?= max(0, min(100, (float)$model['accuracy'] * 100)) ?>%"></div>
                            </div>
                            <strong><?= $formatPercent($model['accuracy']) ?></strong>
                        </td>
                        <td><?= $formatPercent($model['precision']) ?></td>
                        <td><?= $formatPercent($model['recall']) ?></td>
                        <td><?= $formatPercent($model['f1_score']) ?></td>
                        <td><?= $formatPercent($model['cv_mean']) ?></td>
                        <td><?= $model['cv_std'] === null ? 'N/A' : number_format((float)$model['cv_std'], 4) ?></td>
                        <td>
                            <?= !empty($model['training_samples']) ? number_format((int)$model['training_samples']) : 'N/A' ?>
                            /
                            <?= !empty($model['test_samples']) ? number_format((int)$model['test_samples']) : 'N/A' ?>
                        </td>
                        <td><?= !empty($model['model_version']) ? '<span class="badge bg-secondary">' . htmlspecialchars((string)$model['model_version']) . '</span>' : 'N/A' ?></td>
                        <td><?= htmlspecialchars($formatDateTime($model['trained_at'] ?? null)) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="card p-4 h-100">
                <h6 class="mb-3"><i class="bi bi-bar-chart-line me-2 text-primary"></i>Accuracy, Precision, Recall, F1</h6>
                <div style="height:340px;">
                    <canvas id="metricsChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card p-4 h-100">
                <h6 class="mb-3"><i class="bi bi-activity me-2 text-success"></i>Accuracy vs Cross-Validation</h6>
                <div style="height:340px;">
                    <canvas id="stabilityChart"></canvas>
                </div>
            </div>
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
                    <div class="small text-muted">Target Compliance</div>
                    <div class="fw-bold fs-5 text-warning"><?= $modelsAtTarget ?>/<?= count($models) ?> models</div>
                    <div class="small text-muted">Reached the <?= number_format($targetAccuracy * 100, 0) ?>% target</div>
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

<?php if ($mlDashboard['available'] && !empty($models)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const modelRows = <?= json_encode(array_map(function (array $model): array {
    return [
        'name' => $model['model_name'],
        'accuracy' => round(((float)$model['accuracy']) * 100, 2),
        'precision' => $model['precision'] === null ? null : round(((float)$model['precision']) * 100, 2),
        'recall' => $model['recall'] === null ? null : round(((float)$model['recall']) * 100, 2),
        'f1' => $model['f1_score'] === null ? null : round(((float)$model['f1_score']) * 100, 2),
        'cvMean' => $model['cv_mean'] === null ? null : round(((float)$model['cv_mean']) * 100, 2),
    ];
}, $models), JSON_UNESCAPED_SLASHES) ?>;

const labels = modelRows.map((row) => row.name);
const targetAccuracy = <?= json_encode(round($targetAccuracy * 100, 2)) ?>;

new Chart(document.getElementById('metricsChart'), {
    type: 'bar',
    data: {
        labels,
        datasets: [
            {
                label: 'Accuracy',
                data: modelRows.map((row) => row.accuracy),
                backgroundColor: 'rgba(15, 52, 96, 0.85)',
                borderRadius: 8
            },
            {
                label: 'Precision',
                data: modelRows.map((row) => row.precision),
                backgroundColor: 'rgba(233, 69, 96, 0.75)',
                borderRadius: 8
            },
            {
                label: 'Recall',
                data: modelRows.map((row) => row.recall),
                backgroundColor: 'rgba(46, 204, 113, 0.75)',
                borderRadius: 8
            },
            {
                label: 'F1 Score',
                data: modelRows.map((row) => row.f1),
                backgroundColor: 'rgba(245, 166, 35, 0.8)',
                borderRadius: 8
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' }
        },
        scales: {
            y: {
                min: Math.max(0, targetAccuracy - 15),
                max: 100,
                ticks: {
                    callback: (value) => `${value}%`
                }
            }
        }
    }
});

new Chart(document.getElementById('stabilityChart'), {
    type: 'line',
    data: {
        labels,
        datasets: [
            {
                label: 'Accuracy',
                data: modelRows.map((row) => row.accuracy),
                borderColor: '#0f3460',
                backgroundColor: 'rgba(15, 52, 96, 0.15)',
                fill: false,
                tension: 0.25
            },
            {
                label: 'CV Mean',
                data: modelRows.map((row) => row.cvMean),
                borderColor: '#1f8a70',
                backgroundColor: 'rgba(31, 138, 112, 0.15)',
                fill: false,
                tension: 0.25
            },
            {
                label: 'Target',
                data: labels.map(() => targetAccuracy),
                borderColor: '#dc3545',
                borderDash: [6, 6],
                pointRadius: 0,
                fill: false
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' }
        },
        scales: {
            y: {
                min: Math.max(0, targetAccuracy - 15),
                max: 100,
                ticks: {
                    callback: (value) => `${value}%`
                }
            }
        }
    }
});
</script>
<?php endif; ?>
