/**
 * Front-end Currency Converter Calculator scripts.
 * Supports instant client-side calculation for today's rates and REST API fallback for historical rates.
 *
 * @package Curs BNR – Convertor Valutar
 */

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('onuexra-convertor-form');
  if (!form) return;

  const amountInput = document.getElementById('onuexra-amount');
  const fromSelect = document.getElementById('onuexra-from');
  const toSelect = document.getElementById('onuexra-to');
  const dateInput = document.getElementById('onuexra-conv-date');
  const swapBtn = document.getElementById('onuexra-swap-btn');
  const resultDisplay = document.getElementById('onuexra-result-calculation');
  const dateDisplay = document.getElementById('onuexra-result-date');

  let fetchTimeout = null;

  // Format number matching settings
  function formatCurrencyValue(num) {
    if (isNaN(num)) return '-';
    const decimals = onuexraData.decimals !== undefined ? parseInt(onuexraData.decimals) : 4;
    const decSep = onuexraData.decimalSep || ',';
    const thouSep = onuexraData.thousandSep !== undefined ? onuexraData.thousandSep : '.';

    let parts = num.toFixed(decimals).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thouSep);
    return parts.join(decSep);
  }

  // Calculate today's rates client-side
  function calculateClientSide(amount, from, to) {
    const rates = onuexraData.rates;
    if (!rates) return null;

    let fromRate = 1.0;
    let fromMul = 1;
    if (from !== 'RON') {
      if (!rates[from]) return null;
      fromRate = parseFloat(rates[from].rate);
      fromMul = parseInt(rates[from].multiplier) || 1;
    }

    let toRate = 1.0;
    let toMul = 1;
    if (to !== 'RON') {
      if (!rates[to]) return null;
      toRate = parseFloat(rates[to].rate);
      toMul = parseInt(rates[to].multiplier) || 1;
    }

    const valueInRon = amount * (fromRate / fromMul);
    return valueInRon / (toRate / toMul);
  }

  // Core update conversion handler
  function updateConversion() {
    const amount = parseFloat(amountInput.value);
    const from = fromSelect.value;
    const to = toSelect.value;
    const date = dateInput.value;

    if (isNaN(amount) || amount < 0) {
      resultDisplay.textContent = '-';
      dateDisplay.textContent = '';
      return;
    }

    if (from === to) {
      resultDisplay.textContent = `${formatCurrencyValue(amount)} ${to}`;
      dateDisplay.textContent = '';
      return;
    }

    // Determine if we should perform live fetch or instant local calculation
    const todayStr = new Date().toISOString().split('T')[0];
    const isHistorical = date && date !== todayStr;

    if (!isHistorical) {
      // Local Client-side calculation (instantaneous)
      const result = calculateClientSide(amount, from, to);
      if (result !== null) {
        resultDisplay.textContent = `${formatCurrencyValue(amount)} ${from} = ${formatCurrencyValue(result)} ${to}`;
        dateDisplay.textContent = '';
      } else {
        resultDisplay.textContent = '-';
        dateDisplay.textContent = 'Eroare date locale';
      }
    } else {
      // Clear previous timeout and debounce fetch request to avoid flooding API on rapid keystrokes
      if (fetchTimeout) {
        clearTimeout(fetchTimeout);
      }

      resultDisplay.textContent = 'Calculare...';
      dateDisplay.textContent = '';

      fetchTimeout = setTimeout(() => {
        const queryUrl = `${onuexraData.restUrl}/convert?amount=${amount}&from=${from}&to=${to}&date=${date}`;
        
        fetch(queryUrl)
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              resultDisplay.textContent = `${formatCurrencyValue(amount)} ${from} = ${formatCurrencyValue(data.result)} ${to}`;
              
              // Format date nicely
              const dateObj = new Date(data.date);
              const formattedDate = dateObj.toLocaleDateString('ro-RO', { year: 'numeric', month: 'long', day: 'numeric' });
              dateDisplay.textContent = `Curs valabil pentru data de: ${formattedDate}`;
            } else {
              resultDisplay.textContent = 'Indisponibil';
              dateDisplay.textContent = data.message || 'Eroare de conexiune';
            }
          })
          .catch(() => {
            resultDisplay.textContent = 'Eroare';
            dateDisplay.textContent = 'Nu s-a putut efectua conversia.';
          });
      }, 300); // 300ms debounce
    }
  }

  // Swap currencies handler
  swapBtn.addEventListener('click', () => {
    const temp = fromSelect.value;
    fromSelect.value = toSelect.value;
    toSelect.value = temp;
    updateConversion();
  });

  // Attach event listeners
  amountInput.addEventListener('input', updateConversion);
  fromSelect.addEventListener('change', updateConversion);
  toSelect.addEventListener('change', updateConversion);
  dateInput.addEventListener('change', updateConversion);

  // Run initial conversion
  updateConversion();
});
