<?php
// views/admin_analytics.php
function render_analytics_page($db, $url_id) {
    $url = DB::fetch('SELECT * FROM urls WHERE id = ?', [$url_id]);

    if (!$url) {
        echo 'URL not found';
        return;
    }

    // Clicks over time (30 days)
    $clicks_over_time = DB::fetchAll("
        SELECT DATE(clicked_at) as date, COUNT(*) as clicks
        FROM clicks
        WHERE url_id = ? AND clicked_at >= DATE('now', '-30 days')
        GROUP BY DATE(clicked_at)
        ORDER BY date
    ", [$url_id]);

    // Top referrers
    $referrers = DB::fetchAll("
        SELECT referrer, COUNT(*) as clicks
        FROM clicks
        WHERE url_id = ? AND referrer IS NOT NULL AND referrer != ''
        GROUP BY referrer
        ORDER BY clicks DESC
        LIMIT 10
    ", [$url_id]);

    // Recent clicks
    $recent_clicks = DB::fetchAll("
        SELECT clicked_at, referrer, user_agent
        FROM clicks
        WHERE url_id = ?
        ORDER BY clicked_at DESC
        LIMIT 20
    ", [$url_id]);

    // Format clicks data for Chart.js
    $chart_labels = [];
    $chart_data = [];
    foreach ($clicks_over_time as $row) {
        $chart_labels[] = $row['date'];
        $chart_data[] = $row['clicks'];
    }
    $chart_labels_json = json_encode($chart_labels);
    $chart_data_json = json_encode($chart_data);

    // Build referrers table
    $referrers_html = '';
    foreach ($referrers as $ref) {
        $referrers_html .= '<tr class="border-b dark:border-gray-700">';
        $referrers_html .= '<td class="px-4 py-2 text-gray-900 dark:text-gray-100 truncate max-w-md">' . htmlspecialchars($ref['referrer']) . '</td>';
        $referrers_html .= '<td class="px-4 py-2 text-gray-600 dark:text-gray-300">' . $ref['clicks'] . '</td>';
        $referrers_html .= '</tr>';
    }

    // Build recent clicks table
    $clicks_html = '';
    foreach ($recent_clicks as $click) {
        $clicks_html .= '<tr class="border-b dark:border-gray-700">';
        $clicks_html .= '<td class="px-4 py-2 text-gray-600 dark:text-gray-300">' . date('M j, Y g:i A', strtotime($click['clicked_at'])) . '</td>';
        $clicks_html .= '<td class="px-4 py-2 text-gray-900 dark:text-gray-100 truncate max-w-md">' . htmlspecialchars($click['referrer'] ?? 'Direct') . '</td>';
        $clicks_html .= '<td class="px-4 py-2 text-gray-500 dark:text-gray-400 truncate max-w-xs">' . htmlspecialchars($click['user_agent'] ?? '') . '</td>';
        $clicks_html .= '</tr>';
    }

    // Format data for use in heredoc
    $created_at = date('M j, Y', strtotime($url['created_at']));

    $content = <<<HTML
<div>
    <div class="mb-6">
        <a href="/admin/urls" class="text-blue-500 dark:text-blue-400 hover:underline">← Back to URLs</a>
    </div>

    <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6 mb-6">
        <h1 class="text-2xl font-bold mb-2 text-gray-900 dark:text-gray-100">/{$url['short_code']}</h1>
        <p class="text-gray-600 dark:text-gray-400 truncate">{$url['long_url']}</p>
        <div class="mt-4 grid grid-cols-3 gap-4">
            <div>
                <div class="text-gray-500 dark:text-gray-400 text-sm">Total Clicks</div>
                <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{$url['click_count']}</div>
            </div>
            <div>
                <div class="text-gray-500 dark:text-gray-400 text-sm">Created</div>
                <div class="text-lg text-gray-900 dark:text-gray-100">{$created_at}</div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Clicks Over Time (30 days)</h2>
            <canvas id="clicksChart"></canvas>
        </div>

        <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Top Referrers</h2>
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-800">
                        <th class="text-left px-4 py-2">Referrer</th>
                        <th class="text-left px-4 py-2">Clicks</th>
                    </tr>
                </thead>
                <tbody>
                    {$referrers_html}
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6 mt-6">
        <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Recent Clicks</h2>
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50 dark:bg-gray-800">
                    <th class="text-left px-4 py-2">Time</th>
                    <th class="text-left px-4 py-2">Referrer</th>
                    <th class="text-left px-4 py-2">User Agent</th>
                </tr>
            </thead>
            <tbody>
                {$clicks_html}
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Function to get theme-aware colors
function getChartColors() {
    const isDark = document.documentElement.classList.contains('dark');
    return {
        border: isDark ? 'rgb(96, 165, 250)' : 'rgb(59, 130, 246)',
        background: isDark ? 'rgba(96, 165, 250, 0.2)' : 'rgba(59, 130, 246, 0.1)'
    };
}

// Create chart
const colors = getChartColors();
const ctx = document.getElementById('clicksChart').getContext('2d');
const clicksChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: {$chart_labels_json},
        datasets: [{
            label: 'Clicks',
            data: {$chart_data_json},
            borderColor: colors.border,
            backgroundColor: colors.background,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    color: document.documentElement.classList.contains('dark') ? '#9CA3AF' : '#6B7280'
                },
                grid: {
                    color: document.documentElement.classList.contains('dark') ? '#374151' : '#E5E7EB'
                }
            },
            x: {
                ticks: {
                    color: document.documentElement.classList.contains('dark') ? '#9CA3AF' : '#6B7280'
                },
                grid: {
                    color: document.documentElement.classList.contains('dark') ? '#374151' : '#E5E7EB'
                }
            }
        }
    }
});

// Expose update function for theme changes
window.updateChartColors = function() {
    const colors = getChartColors();
    const isDark = document.documentElement.classList.contains('dark');
    clicksChart.data.datasets[0].borderColor = colors.border;
    clicksChart.data.datasets[0].backgroundColor = colors.background;
    clicksChart.options.scales.y.ticks.color = isDark ? '#9CA3AF' : '#6B7280';
    clicksChart.options.scales.y.grid.color = isDark ? '#374151' : '#E5E7EB';
    clicksChart.options.scales.x.ticks.color = isDark ? '#9CA3AF' : '#6B7280';
    clicksChart.options.scales.x.grid.color = isDark ? '#374151' : '#E5E7EB';
    clicksChart.update();
};
</script>
HTML;

    require_once __DIR__ . '/admin_layout.php';
    return render_admin_layout('Analytics: ' . $url['short_code'], $content, '');
}
