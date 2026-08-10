/**
 * Project Charts — Chart.js dashboard widget.
 * Renders trên widget #laca-project-charts-widget.
 * Dữ liệu nhận từ wp_localize_script('lacaProjectCharts').
 */

import { Chart, ArcElement, DoughnutController, BarElement, BarController,
	CategoryScale, LinearScale, Tooltip, Legend } from 'chart.js';

Chart.register(
	ArcElement,
	DoughnutController,
	BarElement,
	BarController,
	CategoryScale,
	LinearScale,
	Tooltip,
	Legend
);

( function () {
	'use strict';

	if ( typeof lacaProjectCharts === 'undefined' ) {
		return;
	}

	const data = lacaProjectCharts;

	// ── Palette ───────────────────────────────────────────────

	const colors = {
		primary     : data.primary   || '#2ea2cc',
		pending     : '#b0b8cc',        // 🕐 Chờ làm     — xám
		inProgress  : '#f5a623',        // 🔨 Đang làm    — cam
		done        : '#3ecf8e',        // ✅ Đã xong     — xanh lá
		maintenance : '#a855f7',        // 🔧 Bảo trì     — tím
		paused      : '#f15d4f',        // ⏸️ Tạm dừng   — đỏ
		grid        : 'rgba(0,0,0,0.06)',
		text        : '#1a1a2e',
	};

	// ── Doughnut — trạng thái project ────────────────────────

	const donutCanvas = document.getElementById( 'laca-chart-status' );
	if ( donutCanvas && data.byStatus ) {
		const statuses = data.byStatus; // { label, count }[]

		const colorMap = {
			pending     : colors.pending,
			in_progress : colors.inProgress,
			done        : colors.done,
			maintenance : colors.maintenance,
			paused      : colors.paused,
		};

		new Chart( donutCanvas, {
			type: 'doughnut',
			data: {
				labels: statuses.map( ( s ) => s.label ),
				datasets: [
					{
						data            : statuses.map( ( s ) => s.count ),
						backgroundColor : statuses.map( ( s ) => colorMap[ s.key ] || colors.draft ),
						borderWidth     : 0,
						hoverOffset     : 6,
					},
				],
			},
			options: {
				responsive      : true,
				cutout          : '68%',
				plugins: {
					legend: {
						position : 'bottom',
						labels: {
							color       : colors.text,
							font        : { size: 12 },
							padding     : 14,
							boxWidth    : 12,
							borderRadius: 6,
							usePointStyle: true,
						},
					},
					tooltip: {
						callbacks: {
							label: ( ctx ) =>
								` ${ ctx.label }: ${ ctx.parsed }`,
						},
					},
				},
			},
		} );
	}

	// ── Bar — projects theo tháng ─────────────────────────────

	const barCanvas = document.getElementById( 'laca-chart-monthly' );
	if ( barCanvas && data.byMonth ) {
		const months = data.byMonth; // { month: 'T1', count: 3 }[]

		new Chart( barCanvas, {
			type: 'bar',
			data: {
				labels: months.map( ( m ) => m.month ),
				datasets: [
					{
						label           : 'Projects',
						data            : months.map( ( m ) => m.count ),
						backgroundColor : colors.primary,
						borderRadius    : 6,
						borderSkipped   : false,
						barPercentage   : 0.6,
					},
				],
			},
			options: {
				responsive : true,
				plugins: {
					legend  : { display: false },
					tooltip : {
						callbacks: {
							label: ( ctx ) => ` ${ ctx.parsed.y } projects`,
						},
					},
				},
				scales: {
					x: {
						grid: { color: colors.grid },
						ticks: {
							color : colors.text,
							font  : { size: 11 },
						},
					},
					y: {
						grid: { color: colors.grid },
						beginAtZero : true,
						ticks: {
							stepSize  : 1,
							color     : colors.text,
							font      : { size: 11 },
						},
					},
				},
			},
		} );
	}

} )();
