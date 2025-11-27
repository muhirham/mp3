@extends('layouts.home')

@section('title','Admin Dashboard')

@section('content')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/countup.js@2.2.0/dist/countUp.min.js"></script>

<style>
  /* ====== Layout ====== */
  .container-dash{padding:18px 12px}
  .header-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
  .grid{display:grid;grid-template-columns:repeat(12,1fr);gap:16px}

  /* ====== Cards ====== */
  .card{background:var(--bg-card,#fff);border-radius:12px;padding:14px;border:1px solid var(--border,#e5e7eb);box-shadow:0 8px 26px rgba(16,24,40,.04)}
  .kpi-card{grid-column:span 3;display:flex;flex-direction:column;gap:8px;min-height:110px}
  .big-card{grid-column:span 6;min-height:360px}
  .small-card{grid-column:span 3;min-height:180px}

  .kpi-top{display:flex;gap:12px;align-items:center}
  .kpi-icon{width:46px;height:46px;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;background:linear-gradient(135deg,#6366f1,#4f46e5);font-weight:700}
  .kpi-value{font-weight:800;font-size:1.6rem;line-height:1}
  .muted{color:#6b7280;font-size:.85rem}
  .small-num{font-weight:700;color:#111827}

  /* ====== Second row (Order + Income) ====== */
  .row-analytics{display:grid;grid-template-columns:repeat(12,1fr);gap:16px;margin-top:16px}
  .order-card{grid-column:span 7}
  .income-card{grid-column:span 5}
  .order-stats{display:flex;gap:18px;align-items:flex-start}
  .order-left{flex:1}
  .order-right{width:200px;display:flex;flex-direction:column;align-items:center;gap:10px}
  .big-num{font-size:2.4rem;font-weight:800;margin-top:6px}

  .cat-list{margin-top:16px;display:flex;flex-direction:column;gap:12px}
  .cat-item{display:flex;align-items:center;gap:12px}
  .cat-icon{width:44px;height:44px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-weight:700}

  .tabs{display:flex;gap:8px;margin-bottom:12px}
  .tab-btn{padding:8px 12px;border-radius:8px;background:transparent;border:1px solid transparent;cursor:pointer}
  .tab-btn.active{background:linear-gradient(90deg,#7c3aed,#4f46e5);color:#fff;box-shadow:0 8px 18px rgba(79,70,229,.12)}

  /* ====== Responsive ====== */
  @media (max-width: 992px){
    .kpi-card{grid-column:span 6}
    .big-card{grid-column:span 12}
    .small-card{grid-column:span 6}
    .order-card,.income-card{grid-column:span 12}
  }
  @media (max-width: 576px){
    .grid{grid-template-columns:repeat(1,1fr)}
  }
</style>

<div class="container-dash">
  {{-- Header --}}
  <div class="header-row">
    <div>
      <h3 class="mb-0">Admin Dashboard</h3>
      <div class="muted">Overview & analytics</div>
    </div>
    <div style="display:flex;gap:12px;align-items:center">
      <div class="muted">Welcome {{ auth()->user()->name ?? 'Admin' }}</div>
      <img src="https://ui-avatars.com/api/?name=AD" style="width:36px;height:36px;border-radius:50%" alt="avatar">
    </div>
  </div>

  {{-- ===== KPI Row ===== --}}
  <div class="grid">
    <div class="card kpi-card">
      <div class="kpi-top">
        <div class="kpi-icon">TX</div>
        <div>
          <div class="kpi-value" id="totalTransactions">0</div>
          <div class="muted">Total Transactions</div>
        </div>
      </div>
      <div class="muted">All transactions • <span id="txChange">—</span></div>
    </div>

    <div class="card kpi-card">
      <div class="kpi-top">
        <div class="kpi-icon">U</div>
        <div>
          <div class="kpi-value" id="totalUsers">0</div>
          <div class="muted">Total Users</div>
        </div>
      </div>
      <div class="muted">Registered users • <span id="userChange">—</span></div>
    </div>

    <div class="card kpi-card">
      <div class="kpi-top">
        <div class="kpi-icon">P</div>
        <div>
          <div class="kpi-value" id="totalProducts">0</div>
          <div class="muted">Total Products</div>
        </div>
      </div>
      <div class="muted">Items in catalog • <span id="prodChange">—</span></div>
    </div>

    <div class="card kpi-card">
      <div class="kpi-top">
        <div class="kpi-icon">W</div>
        <div>
          <div class="kpi-value" id="totalWarehouses">0</div>
          <div class="muted">Warehouses</div>
        </div>
      </div>
      <div class="muted">Locations • <span id="whChange">—</span></div>
    </div>

    {{-- ===== Revenue Line (big) ===== --}}
    <div class="card big-card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
        <div>
          <div class="muted">Total Income</div>
          <div style="font-weight:800;font-size:1.1rem">Yearly report overview</div>
        </div>
        <div style="text-align:right">
          <div class="muted">This month</div>
          <div class="small-num" id="salesThisMonth">Rp 0</div>
        </div>
      </div>
      <div style="height:280px">
        <div id="totalRevenueChart" style="width:100%;height:100%"></div>
      </div>
    </div>

    {{-- ===== Right: 3 small cards ===== --}}
    <div class="card small-card">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <div>
          <div class="muted">Report</div>
          <div style="font-weight:800">Monthly Avg. <span class="muted">$45.57k</span></div>
        </div>
        <div style="text-align:right">
          <div class="muted">Income</div>
          <div class="small-num" id="reportIncome">$42,845</div>
        </div>
      </div>
      <hr style="margin:12px 0;border:none;border-top:1px solid var(--border,#f3f4f6)">
      <div class="muted">Quick stats</div>
      <div style="display:flex;gap:12px;margin-top:8px">
        <div style="flex:1">
          <div class="muted">Sales</div>
          <div style="font-weight:800">482k</div>
        </div>
        <div style="flex:1">
          <div class="muted">Revenue</div>
          <div style="font-weight:800" id="revenueVal">Rp 42,389</div>
        </div>
      </div>
    </div>

    <div class="card small-card">
      <div class="muted">By status</div>
      <div style="height:160px;margin-top:8px">
        <canvas id="donutOrders"></canvas>
      </div>
      <div id="donutLegend" class="muted" style="margin-top:8px"></div>
    </div>

    <div class="card small-card">
      <div class="muted">Transactions</div>
      <div id="transactionsValue" style="font-weight:800;font-size:1.2rem;margin:8px 0">0</div>
      <div style="height:100px"><canvas id="miniBar"></canvas></div>
    </div>
  </div>

  {{-- ===== Order Statistics + Income ===== --}}
  <div class="row-analytics">
    <div class="card order-card">
      <div class="order-stats">
        <div class="order-left">
          <h5 class="mb-1">Order Statistics</h5>
          <div class="muted">42.82k Total Sales</div>
          <div class="big-num" id="totalOrders">0</div>
          <div class="muted">Total Orders</div>

          <div class="cat-list" id="categoryList"></div>
        </div>
        <div class="order-right">
          <div style="width:140px;height:140px;display:flex;align-items:center;justify-content:center">
            <canvas id="ordersDonut" width="140" height="140"></canvas>
          </div>
          <div class="muted">Weekly</div>
          <div id="donutPercent" class="big-num" style="font-size:1.25rem">0%</div>
        </div>
      </div>
    </div>

    <div class="card income-card">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <div>
          <h5 class="mb-0">Income</h5>
          <div class="muted">Total Balance details</div>
        </div>
        <div class="tabs" role="tablist" aria-label="financial tabs">
          <button class="tab-btn active" data-tab="income">Income</button>
          <button class="tab-btn" data-tab="expenses">Expenses</button>
          <button class="tab-btn" data-tab="profit">Profit</button>
        </div>
      </div>

      <div style="margin-top:12px">
        <div style="display:flex;gap:14px;align-items:center;justify-content:space-between">
          <div>
            <div class="muted">Total Balance</div>
            <div style="font-weight:800" id="totalBalance">Rp 0</div>
            <div class="muted" id="balanceChange">+0%</div>
          </div>
          <div style="flex:1;padding-left:18px">
            <div style="height:160px"><canvas id="incomeArea"></canvas></div>
          </div>
        </div>

        <div style="display:flex;gap:12px;align-items:center;margin-top:12px">
          <div style="flex:0 0 110px;text-align:center">
            <div style="font-weight:700" id="expensesThisWeekVal">$0</div>
            <div class="muted" style="font-size:.85rem">Expenses This Week</div>
          </div>
          <div style="width:80px;height:80px"><canvas id="miniDonut" width="80" height="80"></canvas></div>
          <div style="flex:1">
            <div class="muted">This Week Comparison</div>
            <div id="weekCompare" style="font-weight:700">—</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- ===== Scripts ===== --}}
<script>
  // kosongin kalau belum punya endpoint; fallback akan dipakai
  const STATS_API = '';

  const FALLBACK = {
    totals:{ transactions:8258, users:1240, products:582, warehouses:3, sales_this_month:42145 },
    monthly:{ labels:['Feb','Mar','Apr','May','Jun','Jul'], data:[12000,18000,14000,22000,19000,24000] },
    by_status:{ Completed:6200, Pending:1200, Refunded:300, Failed:58 },
    performance:{ Income:75, Earning:62, Sales:80, Growth:70, Conversion:68 },
    transactions_series:[2,3,1,4,2,1,3,2,4,3,5,2],
    category_breakdown:[
      { name:'Electronic', sub:'Mobile, Earbuds, TV', value:'82.5k', color:'#bfdbfe', icon:'📱' },
      { name:'Fashion', sub:'T-shirt, Jeans, Shoes', value:'23.8k', color:'#bbf7d0', icon:'👕' },
      { name:'Decor', sub:'Fine Art, Dining', value:'849',   color:'#fee2e2', icon:'🏡' },
      { name:'Sports', sub:'Football, Cricket Kit', value:'99',     color:'#fce7f3', icon:'🏈' }
    ]
  };

  let chartRevenue, chartDonut, chartMiniBar, ordersDonut, incomeArea, miniDonut;

  const rupiah = v => 'Rp ' + Number(v||0).toLocaleString('id-ID',{maximumFractionDigits:0});
  const safeNum = v => (v===null||v===undefined||isNaN(Number(v)))?0:Number(v);

  function createGradient(ctx,h,c1,c2){ const g=ctx.createLinearGradient(0,0,0,h); g.addColorStop(0,c1); g.addColorStop(1,c2); return g; }
  function animateKPI(id,val){ try{ new CountUp.CountUp(id, val, {duration:1.2, separator:'.'}).start(); }catch{ document.getElementById(id).textContent = val; } }

  function renderRevenue(labels,data){
    const el = document.getElementById('totalRevenueChart'); if(!el) return;
    if(!document.getElementById('chart_totalRevenue')) el.innerHTML='<canvas id="chart_totalRevenue" style="width:100%;height:100%"></canvas>';
    const ctx = document.getElementById('chart_totalRevenue').getContext('2d');
    if(chartRevenue){ chartRevenue.data.labels=labels; chartRevenue.data.datasets[0].data=data; chartRevenue.update(); return; }
    const grad = createGradient(ctx,280,'rgba(79,70,229,.18)','rgba(79,70,229,.02)');
    chartRevenue = new Chart(ctx,{ type:'line', data:{ labels, datasets:[{ data, borderWidth:3, borderColor:'#4f46e5', backgroundColor:grad, fill:true, pointRadius:4, pointBackgroundColor:'#fff', tension:.35 }]}, options:{ responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{ x:{grid:{display:false}}, y:{grid:{color:'rgba(15,23,42,.06)'}, ticks:{ callback:v=> 'Rp '+Number(v).toLocaleString('id-ID') }}} });
  }

  function renderDonut(labels,data){
    const ctx = document.getElementById('donutOrders').getContext('2d');
    const colors = ['#10b981','#f59e0b','#ef4444','#3b82f6','#a78bfa'];
    if(chartDonut){ chartDonut.data.labels=labels; chartDonut.data.datasets[0].data=data; chartDonut.update(); }
    else chartDonut = new Chart(ctx,{ type:'doughnut', data:{ labels, datasets:[{ data, backgroundColor:colors.slice(0,labels.length) }]}, options:{ responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}} }});
    document.getElementById('donutLegend').innerHTML = labels.map((l,i)=>`<span style="display:inline-block;margin-right:10px"><span style="width:10px;height:10px;background:${colors[i]};display:inline-block;margin-right:6px;border-radius:3px;vertical-align:middle"></span>${l} (${data[i]})</span>`).join('');
  }

  function renderMiniBar(labels,data){
    const ctx = document.getElementById('miniBar').getContext('2d');
    if(chartMiniBar){ chartMiniBar.data.labels=labels; chartMiniBar.data.datasets[0].data=data; chartMiniBar.update(); return; }
    chartMiniBar = new Chart(ctx,{ type:'bar', data:{ labels, datasets:[{ data, backgroundColor:'rgba(59,130,246,.9)', borderRadius:6 }]}, options:{ responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}, tooltip:{enabled:false}}, scales:{ x:{display:false}, y:{display:false}} });
  }

  function renderOrdersDonut(labels,values,percent){
    const ctx = document.getElementById('ordersDonut').getContext('2d');
    const colors = ['#22c55e','#06b6d4','#f97316','#ef4444','#6366f1'];
    if(ordersDonut) ordersDonut.destroy();
    ordersDonut = new Chart(ctx,{ type:'doughnut', data:{ labels, datasets:[{ data:values, backgroundColor:colors.slice(0,labels.length), hoverOffset:8 }]}, options:{ responsive:false, cutout:'72%', plugins:{legend:{display:false}} }});
    document.getElementById('donutPercent').textContent = percent + '%';
  }

  function renderIncomeArea(labels,data,color='#6366f1'){
    const ctx = document.getElementById('incomeArea').getContext('2d');
    if(incomeArea) incomeArea.destroy();
    const grad = createGradient(ctx,160,'rgba(99,102,241,.14)','rgba(99,102,241,.02)');
    incomeArea = new Chart(ctx,{ type:'line', data:{ labels, datasets:[{ data, borderColor:color, backgroundColor:grad, fill:true, tension:.35, pointRadius:3 }]}, options:{ responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{ y:{ ticks:{ callback:v=> 'Rp '+Number(v).toLocaleString('id-ID') }}} });
  }

  function renderMiniDonut(value,total){
    const ctx = document.getElementById('miniDonut').getContext('2d');
    if(miniDonut) miniDonut.destroy();
    miniDonut = new Chart(ctx,{ type:'doughnut', data:{ datasets:[{ data:[value, Math.max(0,total-value)], backgroundColor:['#4f46e5','#e6e9f6'] }]}, options:{ responsive:false, cutout:'70%', plugins:{legend:{display:false}} }});
  }

  function fillCategoryList(arr){
    const wrap = document.getElementById('categoryList'); wrap.innerHTML='';
    arr.forEach(it=>{
      const div=document.createElement('div'); div.className='cat-item';
      div.innerHTML = `
        <div class="cat-icon" style="background:${it.color||'#e2e8f0'}">${it.icon||it.name.charAt(0)}</div>
        <div style="flex:1">
          <div style="font-weight:700">${it.name}</div>
          <small class="muted">${it.sub || ''}</small>
        </div>
        <div style="font-weight:700">${it.value}</div>`;
      wrap.appendChild(div);
    });
  }

  async function fetchStats(){
    if(!STATS_API) return FALLBACK; // belum ada endpoint -> fallback
    try{
      const res = await fetch(STATS_API,{headers:{'X-Requested-With':'XMLHttpRequest'}});
      if(!res.ok) throw new Error();
      const j = await res.json();
      return j?.totals ? j : FALLBACK;
    }catch{ return FALLBACK; }
  }

  async function updateUI(){
    const p = await fetchStats();

    const totals = p.totals || FALLBACK.totals;
    const monthly = p.monthly || FALLBACK.monthly;
    const byStatus = p.by_status || FALLBACK.by_status;

    // KPI
    animateKPI('totalTransactions', safeNum(totals.transactions));
    animateKPI('totalUsers',       safeNum(totals.users));
    animateKPI('totalProducts',    safeNum(totals.products));
    animateKPI('totalWarehouses',  safeNum(totals.warehouses));
    document.getElementById('salesThisMonth').textContent = rupiah(safeNum(totals.sales_this_month));
    document.getElementById('reportIncome').textContent   = p.report_income ?? '$42,845';
    document.getElementById('revenueVal').textContent     = rupiah(p.revenue_val ?? 42389);
    document.getElementById('transactionsValue').textContent = (totals.transactions||0).toLocaleString();

    // Charts
    renderRevenue((monthly.labels||[]), (monthly.data||[]).map(safeNum));

    const statusLabels = Object.keys(byStatus);
    const statusValues = Object.values(byStatus).map(safeNum);
    renderDonut(statusLabels, statusValues);

    // Mini bar
    const tx = (p.transactions_series||FALLBACK.transactions_series).slice(-6).map(safeNum);
    renderMiniBar(Array.from({length:tx.length},(_,i)=>i+1), tx);

    // Order donut right
    const totalStatus = statusValues.reduce((a,b)=>a+b,0)||1;
    const completed = byStatus.Completed ?? byStatus.completed ?? 0;
    renderOrdersDonut(statusLabels, statusValues, Math.round((completed/totalStatus)*100));

    // Category list
    fillCategoryList(p.category_breakdown?.length ? p.category_breakdown : FALLBACK.category_breakdown);

    // Income area + small donut
    const labels = monthly.labels || [];
    const incomeData = (monthly.data||[]).map(safeNum);
    const expensesData = incomeData.map(v=>Math.round(v*0.6));
    const profitData = incomeData.map((v,i)=>Math.max(0, v-expensesData[i]));
    renderIncomeArea(labels, incomeData, '#4f46e5');

    const totalBalance = incomeData.reduce((a,b)=>a+b,0);
    document.getElementById('totalBalance').textContent = rupiah(totalBalance);
    const lastIncome = incomeData.at(-1) || 0;
    const expensesThisWeek = Math.round(lastIncome*0.08);
    document.getElementById('expensesThisWeekVal').textContent = '$'+expensesThisWeek;
    renderMiniDonut(expensesThisWeek, Math.max(1,lastIncome));
    document.getElementById('weekCompare').textContent = expensesThisWeek ? ('$'+expensesThisWeek+' less than last week') : '—';

    // Tabs
    document.querySelectorAll('.tab-btn').forEach(btn=>{
      btn.onclick = () => {
        document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
        btn.classList.add('active');
        const tab = btn.dataset.tab;
        if(tab==='income')   renderIncomeArea(labels, incomeData,   '#4f46e5');
        if(tab==='expenses') renderIncomeArea(labels, expensesData, '#f59e0b');
        if(tab==='profit')   renderIncomeArea(labels, profitData,   '#10b981');
      };
    });
  }

  document.addEventListener('DOMContentLoaded', updateUI);
</script>
@endsection
