/**
 * Curs BNR – Convertor Valutar: Interactive Financial Chart Engine.
 * Lightweight, zero-dependency SVG chart with cubic Bézier smoothing,
 * interactive scrubbing crosshair, floating tooltip, and period switcher.
 */

/* global onuexraChartData */

( function () {
	'use strict';

	// In-memory cache to make period switching instant
	const dataCache = new Map();

	/**
	 * Catmull-Rom to Cubic Bézier spline helper.
	 * Generates silky smooth SVG curve path from coordinate points.
	 *
	 * @param {Array} points Array of point coordinates.
	 * @return {string} SVG path string.
	 */
	function pointsToSvgPath( points ) {
		if ( ! points || points.length === 0 ) {
			return '';
		}
		if ( points.length === 1 ) {
			return `M ${ points[ 0 ].x } ${ points[ 0 ].y }`;
		}
		if ( points.length === 2 ) {
			return `M ${ points[ 0 ].x } ${ points[ 0 ].y } L ${ points[ 1 ].x } ${ points[ 1 ].y }`;
		}

		let d = `M ${ points[ 0 ].x.toFixed( 1 ) } ${ points[ 0 ].y.toFixed(
			1
		) }`;

		for ( let i = 0; i < points.length - 1; i++ ) {
			const p0 = i > 0 ? points[ i - 1 ] : points[ i ];
			const p1 = points[ i ];
			const p2 = points[ i + 1 ];
			const p3 = i < points.length - 2 ? points[ i + 2 ] : p2;

			// Catmull-Rom to Cubic Bezier conversion factor (tension = 0.2)
			const tension = 0.2;
			const cp1x = p1.x + ( p2.x - p0.x ) * tension;
			const cp1y = p1.y + ( p2.y - p0.y ) * tension;
			const cp2x = p2.x - ( p3.x - p1.x ) * tension;
			const cp2y = p2.y - ( p3.y - p1.y ) * tension;

			d += ` C ${ cp1x.toFixed( 1 ) } ${ cp1y.toFixed(
				1
			) }, ${ cp2x.toFixed( 1 ) } ${ cp2y.toFixed( 1 ) }, ${ p2.x.toFixed(
				1
			) } ${ p2.y.toFixed( 1 ) }`;
		}

		return d;
	}

	/**
	 * Escape string for safe HTML insertion.
	 *
	 * @param {string} str Input string.
	 * @return {string} Escaped string.
	 */
	function escapeHtml( str ) {
		const div = document.createElement( 'div' );
		div.textContent = str;
		return div.innerHTML;
	}

	/**
	 * Initialize a single chart instance.
	 *
	 * @param {HTMLElement} wrapper Chart container element.
	 */
	function initChartInstance( wrapper ) {
		if ( wrapper.dataset.chartInitialized === 'true' ) {
			return;
		}
		wrapper.dataset.chartInitialized = 'true';

		const chartId =
			wrapper.id ||
			`onuexra-chart-${ Math.random().toString( 36 ).substr( 2, 9 ) }`;
		wrapper.id = chartId;

		let currentCurrency = wrapper.dataset.currency || 'EUR';
		let currentPeriod = wrapper.dataset.period || '1luna';
		const height = parseInt( wrapper.dataset.height, 10 ) || 300;

		const svgContainer = wrapper.querySelector(
			'.onuexra-chart-canvas-container'
		);
		const headerValue = wrapper.querySelector(
			'.onuexra-chart-current-value'
		);
		const headerDiff = wrapper.querySelector(
			'.onuexra-chart-current-diff'
		);
		const statMin = wrapper.querySelector( '.onuexra-stat-min' );
		const statMax = wrapper.querySelector( '.onuexra-stat-max' );
		const statAvg = wrapper.querySelector( '.onuexra-stat-avg' );
		const statChange = wrapper.querySelector( '.onuexra-stat-change' );
		const statPeriod = wrapper.querySelector(
			'.onuexra-stat-period-dates'
		);
		const loader = wrapper.querySelector( '.onuexra-chart-loader' );
		const currencySelect = wrapper.querySelector(
			'.onuexra-chart-currency-select'
		);
		const periodButtons = wrapper.querySelectorAll( '.onuexra-period-btn' );

		let activeData = null;
		let coords = [];

		// Parse initial embedded data if available
		const initialScript = wrapper.querySelector(
			'.onuexra-initial-chart-data'
		);
		if ( initialScript ) {
			try {
				const parsed = JSON.parse( initialScript.textContent );
				if ( parsed && parsed.success ) {
					activeData = parsed;
					const cacheKey = `${ currentCurrency }_${ currentPeriod }`;
					dataCache.set( cacheKey, parsed );
				}
			} catch ( e ) {
				// Fallback to fetch
			}
		}

		function showLoader() {
			if ( loader ) {
				loader.classList.add( 'is-active' );
			}
		}

		function hideLoader() {
			if ( loader ) {
				loader.classList.remove( 'is-active' );
			}
		}

		function showError( message ) {
			if ( ! svgContainer ) {
				return;
			}
			svgContainer.innerHTML = `<div class="onuexra-chart-empty-msg">${ escapeHtml(
				message
			) }</div>`;
		}

		/**
		 * Fetch chart data via REST API.
		 *
		 * @param {string} currency Currency code.
		 * @param {string} period   Period code.
		 */
		function loadData( currency, period ) {
			const cacheKey = `${ currency }_${ period }`;
			if ( dataCache.has( cacheKey ) ) {
				renderChart( dataCache.get( cacheKey ) );
				return;
			}

			showLoader();
			const restBase =
				typeof onuexraChartData !== 'undefined' &&
				onuexraChartData.restUrl
					? onuexraChartData.restUrl
					: '/wp-json/onuexra/v1';
			const url = `${ restBase }/chart-data?currency=${ encodeURIComponent(
				currency
			) }&period=${ encodeURIComponent( period ) }`;

			fetch( url )
				.then( ( res ) => {
					if ( ! res.ok ) {
						throw new Error( 'Data unavailable' );
					}
					return res.json();
				} )
				.then( ( data ) => {
					hideLoader();
					if ( data && data.success ) {
						dataCache.set( cacheKey, data );
						renderChart( data );
					} else {
						showError(
							data.message || 'Nu există date disponibile.'
						);
					}
				} )
				.catch( () => {
					hideLoader();
					showError( 'Eroare la încărcarea datelor istorice.' );
				} );
		}

		/**
		 * Render SVG chart and update stats cards.
		 *
		 * @param {Object} data Chart data object.
		 */
		function renderChart( data ) {
			activeData = data;
			if ( ! svgContainer ) {
				return;
			}

			const points = data.points;
			if ( ! points || points.length === 0 ) {
				showError(
					'Nu există date disponibile pentru perioada selectată.'
				);
				return;
			}

			// Update Header stats
			if ( headerValue ) {
				headerValue.textContent = `${ data.formatted_current } RON`;
			}
			if ( headerDiff ) {
				headerDiff.textContent = `${ data.formatted_diff } (${ data.formatted_diff_pct })`;
				headerDiff.className = `onuexra-chart-current-diff ${
					data.is_positive ? 'onuexra-diff-up' : 'onuexra-diff-down'
				}`;
			}

			// Update Summary Cards
			if ( statMin ) {
				statMin.textContent = `${ data.formatted_min } RON`;
			}
			if ( statMax ) {
				statMax.textContent = `${ data.formatted_max } RON`;
			}
			if ( statAvg ) {
				statAvg.textContent = `${ data.formatted_avg } RON`;
			}
			if ( statChange ) {
				statChange.textContent = `${ data.formatted_diff } (${ data.formatted_diff_pct })`;
				statChange.className = `onuexra-stat-value onuexra-stat-change ${
					data.is_positive ? 'onuexra-text-up' : 'onuexra-text-down'
				}`;
			}
			if ( statPeriod && points.length > 0 ) {
				statPeriod.textContent = `${ points[ 0 ].formatted_date } – ${
					points[ points.length - 1 ].formatted_date
				}`;
			}

			// Compute SVG Coordinates
			const svgWidth = 800;
			const svgHeight = height;
			const padTop = 30;
			const padBottom = 40;
			const padLeft = 20;
			const padRight = 75;

			const rates = points.map( ( p ) => p.rate );
			let minRate = Math.min( ...rates );
			let maxRate = Math.max( ...rates );

			if ( minRate === maxRate ) {
				minRate -= minRate * 0.005;
				maxRate += maxRate * 0.005;
			}

			const range = maxRate - minRate;
			const yMin = minRate - range * 0.1;
			const yMax = maxRate + range * 0.1;
			const yRange = yMax - yMin;

			const plotWidth = svgWidth - padLeft - padRight;
			const plotHeight = svgHeight - padTop - padBottom;

			coords = points.map( ( p, idx ) => {
				const x =
					padLeft + ( idx / ( points.length - 1 || 1 ) ) * plotWidth;
				const y =
					padTop +
					plotHeight -
					( ( p.rate - yMin ) / ( yRange || 1 ) ) * plotHeight;
				return { x, y, point: p };
			} );

			const linePath = pointsToSvgPath( coords );
			const firstCoord = coords[ 0 ];
			const lastCoord = coords[ coords.length - 1 ];
			const zeroY = padTop + plotHeight;
			const areaPath = `${ linePath } L ${ lastCoord.x.toFixed(
				1
			) } ${ zeroY } L ${ firstCoord.x.toFixed( 1 ) } ${ zeroY } Z`;

			const isPositive = data.is_positive;
			const trendColorVar = isPositive
				? 'var(--onuexra-success, #10b981)'
				: 'var(--onuexra-danger, #ef4444)';
			const gradientId = `onuexra-grad-${ chartId }`;

			const guideSteps = [
				{
					rate: minRate,
					label: data.formatted_min,
					y:
						padTop +
						plotHeight -
						( ( minRate - yMin ) / yRange ) * plotHeight,
				},
				{
					rate: ( minRate + maxRate ) / 2,
					label: data.formatted_avg,
					y:
						padTop +
						plotHeight -
						( ( ( minRate + maxRate ) / 2 - yMin ) / yRange ) *
							plotHeight,
				},
				{
					rate: maxRate,
					label: data.formatted_max,
					y:
						padTop +
						plotHeight -
						( ( maxRate - yMin ) / yRange ) * plotHeight,
				},
			];

			let guidesSvg = '';
			guideSteps.forEach( ( g ) => {
				guidesSvg += `
          <g class="onuexra-guide-group">
            <line x1="${ padLeft }" y1="${ g.y.toFixed( 1 ) }" x2="${
				svgWidth - padRight
			}" y2="${ g.y.toFixed( 1 ) }" class="onuexra-guide-line" />
            <text x="${ svgWidth - padRight + 8 }" y="${ ( g.y + 4 ).toFixed(
				1
			) }" class="onuexra-guide-label">${ g.label }</text>
          </g>
        `;
			} );

			let xLabelsSvg = '';
			const totalPoints = points.length;
			const numLabels = Math.min( 5, totalPoints );
			const step =
				totalPoints > 1
					? Math.floor( ( totalPoints - 1 ) / ( numLabels - 1 ) )
					: 1;

			for ( let i = 0; i < numLabels; i++ ) {
				const ptIdx = i === numLabels - 1 ? totalPoints - 1 : i * step;
				const pt = points[ ptIdx ];
				const coord = coords[ ptIdx ];
				if ( pt && coord ) {
					xLabelsSvg += `
            <text x="${ coord.x.toFixed( 1 ) }" y="${ (
				svgHeight - 12
			).toFixed( 1 ) }" text-anchor="middle" class="onuexra-xaxis-label">
              ${ pt.short_date }
            </text>
          `;
				}
			}

			const svgMarkup = `
        <svg viewBox="0 0 ${ svgWidth } ${ svgHeight }" class="onuexra-chart-svg" preserveAspectRatio="none">
          <defs>
            <linearGradient id="${ gradientId }" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="${ trendColorVar }" stop-opacity="0.28" />
              <stop offset="100%" stop-color="${ trendColorVar }" stop-opacity="0.0" />
            </linearGradient>
          </defs>

          <!-- Grid lines -->
          ${ guidesSvg }

          <!-- Area fill -->
          <path d="${ areaPath }" fill="url(#${ gradientId })" class="onuexra-area-path" />

          <!-- Main line -->
          <path d="${ linePath }" fill="none" stroke="${ trendColorVar }" class="onuexra-line-path" />

          <!-- X axis dates -->
          ${ xLabelsSvg }

          <!-- Interactive hover elements -->
          <line x1="0" y1="${ padTop }" x2="0" y2="${ zeroY }" class="onuexra-crosshair-line" style="display: none;" />
          <circle cx="0" cy="0" r="5.5" class="onuexra-crosshair-dot" fill="${ trendColorVar }" style="display: none;" />
        </svg>

        <!-- Floating Tooltip Card -->
        <div class="onuexra-chart-tooltip" style="display: none;">
          <div class="onuexra-tooltip-date"></div>
          <div class="onuexra-tooltip-rate"></div>
          <div class="onuexra-tooltip-diff"></div>
        </div>
      `;

			svgContainer.innerHTML = svgMarkup;
			attachInteractivity();
		}

		/**
		 * Attach hover and touch scrubbing listeners.
		 */
		function attachInteractivity() {
			const svg = svgContainer.querySelector( '.onuexra-chart-svg' );
			const crosshair = svgContainer.querySelector(
				'.onuexra-crosshair-line'
			);
			const dot = svgContainer.querySelector( '.onuexra-crosshair-dot' );
			const tooltip = svgContainer.querySelector(
				'.onuexra-chart-tooltip'
			);
			if (
				! svg ||
				! crosshair ||
				! dot ||
				! tooltip ||
				coords.length === 0
			) {
				return;
			}

			const tooltipDate = tooltip.querySelector(
				'.onuexra-tooltip-date'
			);
			const tooltipRate = tooltip.querySelector(
				'.onuexra-tooltip-rate'
			);
			const tooltipDiff = tooltip.querySelector(
				'.onuexra-tooltip-diff'
			);

			function onScrub( clientX ) {
				const rect = svg.getBoundingClientRect();
				if ( rect.width <= 0 ) {
					return;
				}

				const relX = clientX - rect.left;
				const normalizedX = ( relX / rect.width ) * 800;

				let nearest = coords[ 0 ];
				let minDist = Math.abs( coords[ 0 ].x - normalizedX );

				for ( let i = 1; i < coords.length; i++ ) {
					const dist = Math.abs( coords[ i ].x - normalizedX );
					if ( dist < minDist ) {
						minDist = dist;
						nearest = coords[ i ];
					}
				}

				if ( ! nearest || ! nearest.point ) {
					return;
				}

				crosshair.setAttribute( 'x1', nearest.x );
				crosshair.setAttribute( 'x2', nearest.x );
				crosshair.style.display = 'block';

				dot.setAttribute( 'cx', nearest.x );
				dot.setAttribute( 'cy', nearest.y );
				dot.style.display = 'block';

				const pt = nearest.point;
				tooltipDate.textContent = pt.formatted_date;
				tooltipRate.textContent = `${ pt.formatted_rate } RON`;

				const startRate = activeData ? activeData.start_rate : pt.rate;
				const diffFromStart = pt.rate - startRate;
				const pctFromStart =
					startRate > 0 ? ( diffFromStart / startRate ) * 100 : 0;
				const sign = diffFromStart >= 0 ? '+' : '';
				const decSep =
					( typeof onuexraChartData !== 'undefined' &&
						onuexraChartData.decimalSep ) ||
					',';
				const formattedPct = `${ sign }${ pctFromStart
					.toFixed( 2 )
					.replace( '.', decSep ) }%`;

				tooltipDiff.textContent = `${ formattedPct } vs. început perioadă`;
				tooltipDiff.className = `onuexra-tooltip-diff ${
					diffFromStart >= 0 ? 'onuexra-text-up' : 'onuexra-text-down'
				}`;

				const pointDomX = ( nearest.x / 800 ) * rect.width;
				const pointDomY = ( nearest.y / height ) * rect.height;

				tooltip.style.display = 'block';
				const tooltipWidth = tooltip.offsetWidth || 150;
				let leftPos = pointDomX - tooltipWidth / 2;

				if ( leftPos < 10 ) {
					leftPos = 10;
				}
				if ( leftPos + tooltipWidth > rect.width - 10 ) {
					leftPos = rect.width - tooltipWidth - 10;
				}

				let topPos = pointDomY - 65;
				if ( topPos < 10 ) {
					topPos = pointDomY + 20;
				}

				tooltip.style.left = `${ leftPos }px`;
				tooltip.style.top = `${ topPos }px`;

				if ( headerValue ) {
					headerValue.textContent = `${ pt.formatted_rate } RON`;
				}
			}

			function onLeave() {
				crosshair.style.display = 'none';
				dot.style.display = 'none';
				tooltip.style.display = 'none';

				if ( headerValue && activeData ) {
					headerValue.textContent = `${ activeData.formatted_current } RON`;
				}
			}

			svg.addEventListener( 'mousemove', ( e ) => onScrub( e.clientX ) );
			svg.addEventListener( 'mouseleave', onLeave );

			svg.addEventListener(
				'touchstart',
				( e ) => {
					if ( e.touches && e.touches[ 0 ] ) {
						onScrub( e.touches[ 0 ].clientX );
					}
				},
				{ passive: true }
			);

			svg.addEventListener(
				'touchmove',
				( e ) => {
					if ( e.touches && e.touches[ 0 ] ) {
						onScrub( e.touches[ 0 ].clientX );
					}
				},
				{ passive: true }
			);

			svg.addEventListener( 'touchend', onLeave );
		}

		if ( currencySelect ) {
			currencySelect.addEventListener( 'change', () => {
				currentCurrency = currencySelect.value;
				wrapper.dataset.currency = currentCurrency;
				loadData( currentCurrency, currentPeriod );
			} );
		}

		periodButtons.forEach( ( btn ) => {
			btn.addEventListener( 'click', ( e ) => {
				e.preventDefault();
				const newPeriod = btn.dataset.period;
				if ( ! newPeriod || newPeriod === currentPeriod ) {
					return;
				}

				periodButtons.forEach( ( b ) =>
					b.classList.remove( 'is-active' )
				);
				btn.classList.add( 'is-active' );

				currentPeriod = newPeriod;
				wrapper.dataset.period = currentPeriod;
				loadData( currentCurrency, currentPeriod );
			} );
		} );

		if ( activeData ) {
			renderChart( activeData );
		} else {
			loadData( currentCurrency, currentPeriod );
		}
	}

	document.addEventListener( 'DOMContentLoaded', () => {
		const charts = document.querySelectorAll( '.onuexra-chart-wrapper' );
		charts.forEach( initChartInstance );
	} );

	window.onuexraInitCharts = function () {
		const charts = document.querySelectorAll( '.onuexra-chart-wrapper' );
		charts.forEach( initChartInstance );
	};
} )();
