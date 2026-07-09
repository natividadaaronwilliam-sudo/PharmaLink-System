const salesCtx = document.getElementById('salesChart');
const categoryCtx = document.getElementById('categoryChart');

new Chart(salesCtx, {
  type: 'line',
  data: {
    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
    datasets: [{
      label: 'Sales (₱)',
      data: [12000, 15000, 18000, 16000, 20000, 23000],
      borderColor: '#1e8a4b',
      backgroundColor: 'rgba(30,138,75,0.2)',
      tension: 0.3
    }]
  }
});

new Chart(categoryCtx, {
  type: 'doughnut',
  data: {
    labels: ['Antibiotics', 'Vitamins', 'Painkillers', 'Others'],
    datasets: [{
      data: [35, 25, 20, 20],
      backgroundColor: ['#1e8a4b', '#4ade80', '#86efac', '#bbf7d0']
    }]
  }
});