<script>
(function () {
    const source = document.getElementById('em-chart-payload');
    if (!source || typeof Chart === 'undefined') return;
    const payload = JSON.parse(source.textContent);
    const series = payload.series;
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    const number = new Intl.NumberFormat('id-ID');
    const shortDate = date => new Intl.DateTimeFormat('id-ID', {day:'numeric',month:'short'}).format(new Date(date+'T12:00:00'));
    const tooltipDate = date => new Intl.DateTimeFormat('id-ID', {day:'numeric',month:'long',year:'numeric'}).format(new Date(date+'T12:00:00'));
    const instances = [];
    const initial = new URL(window.location.href);
    let mode = initial.searchParams.get('chart_mode') === 'daily' ? 'daily' : 'cumulative';
    let range = initial.searchParams.get('chart_days') === '30' ? 30 : 90;
    const plotted = values => {
        let total = 0;
        return mode === 'daily' ? values : values.map(value => (total += value));
    };
    const font = getComputedStyle(document.querySelector('.em')).fontFamily;
    const animation = () => reduced.matches ? false : {duration:850,easing:'easeOutQuart'};
    const tooltip = {
        backgroundColor:'#fff',titleColor:'#174773',bodyColor:'#59718d',borderColor:'#dce8f7',borderWidth:1,
        padding:12,cornerRadius:9,displayColors:true,boxPadding:5,
        titleFont:{family:font,size:11,weight:'600'},bodyFont:{family:font,size:11},
    };
    let trend = null;
    const trendCanvas = document.getElementById('em-trend-chart');
    if (trendCanvas) {
        trend = new Chart(trendCanvas, {
            type:'line',
            data:{labels:series.dates,datasets:[
                {label:'Mulai',data:series.started,borderColor:'#307fe2',backgroundColor:context=>{
                    const area=context.chart.chartArea;
                    if(!area)return '#307fe212';
                    const gradient=context.chart.ctx.createLinearGradient(0,area.top,0,area.bottom);
                    gradient.addColorStop(0,'#307fe23d');gradient.addColorStop(1,'#307fe200');return gradient;
                },fill:true,borderWidth:2.6,tension:.32,cubicInterpolationMode:'monotone',pointRadius:0,pointHoverRadius:5,pointHitRadius:14,pointHoverBackgroundColor:'#fff',pointHoverBorderWidth:2},
                {label:'Selesai',data:series.completed,borderColor:'#71c5e8',backgroundColor:'#71c5e80a',fill:true,borderWidth:2.5,tension:.32,cubicInterpolationMode:'monotone',pointRadius:0,pointHoverRadius:5,pointHitRadius:14,pointHoverBackgroundColor:'#fff',pointHoverBorderWidth:2},
            ]},
            options:{responsive:true,maintainAspectRatio:false,animation:animation(),interaction:{mode:'index',intersect:false},
                layout:{padding:{top:10,right:7}},
                plugins:{legend:{display:false},tooltip:{...tooltip,callbacks:{title:items=>tooltipDate(items[0].label),label:item=>item.dataset.label+': '+number.format(item.raw)+' prospek'+(mode==='cumulative'?' (akumulasi)':'')}}},
                scales:{x:{border:{display:false},grid:{display:false},ticks:{autoSkip:true,maxTicksLimit:7,maxRotation:0,color:'#8297ad',font:{family:font,size:10},callback:function(value){return shortDate(this.getLabelForValue(value));}}},
                    y:{beginAtZero:true,border:{display:false,dash:[3,5]},grid:{color:'#eaf1f9',drawTicks:false},ticks:{precision:0,maxTicksLimit:5,padding:10,color:'#8297ad',font:{family:font,size:10}}}},
            },
        });
        instances.push(trend);
    }
    function selectRange(days, saveState = false) {
        range = days;
        const dates=series.dates.slice(-days),started=series.started.slice(-days),completed=series.completed.slice(-days);
        document.querySelector('[data-em-started]').textContent=number.format(started.reduce((a,b)=>a+b,0));
        document.querySelector('[data-em-completed]').textContent=number.format(completed.reduce((a,b)=>a+b,0));
        document.querySelector('[data-em-range]').textContent=shortDate(dates[0])+' – '+shortDate(dates[dates.length-1]);
        document.querySelectorAll('[data-em-days]').forEach(button=>button.setAttribute('aria-pressed',String(Number(button.dataset.emDays)===days)));
        document.querySelectorAll('[data-em-chart-mode]').forEach(button=>button.setAttribute('aria-pressed',String(button.dataset.emChartMode===mode)));
        document.querySelector('[data-em-trend-caption]').textContent=mode==='daily'?'Jumlah aktivitas per hari menurut tanggal mulai dan selesai.':'Akumulasi tanggal mulai dan selesai dalam rentang terpilih.';
        if(trend){trend.data.labels=dates;trend.data.datasets[0].data=plotted(started);trend.data.datasets[1].data=plotted(completed);trend.update(reduced.matches?'none':undefined);}
        if(saveState){const url=new URL(window.location.href);url.searchParams.set('chart_days',String(days));url.searchParams.set('chart_mode',mode);window.history.replaceState(null,'',url);}
    }
    document.querySelectorAll('[data-em-days]').forEach(button=>button.addEventListener('click',()=>selectRange(Number(button.dataset.emDays),true)));
    document.querySelectorAll('[data-em-chart-mode]').forEach(button=>button.addEventListener('click',()=>{mode=button.dataset.emChartMode;selectRange(range,true);}));
    selectRange(range);
    const statusCanvas=document.getElementById('em-status-chart');
    if(statusCanvas){
        const values=Object.values(payload.statuses),total=values.reduce((a,b)=>a+b,0);
        instances.push(new Chart(statusCanvas,{
            type:'doughnut',data:{labels:total?Object.keys(payload.statuses):['Belum ada prospek'],datasets:[{data:total?values:[1],backgroundColor:total?['#d9e8f9','#307fe2','#e3b761','#0a4d98','#a9b6c6']:['#edf3fa'],borderColor:'#fff',borderWidth:4,borderRadius:5,hoverOffset:4,spacing:1}]},
            options:{responsive:true,maintainAspectRatio:false,cutout:'77%',animation:animation(),layout:{padding:8},
                plugins:{legend:{display:false},tooltip:{...tooltip,enabled:total>0,callbacks:{label:item=>number.format(item.raw)+' prospek ('+(item.raw/total*100).toLocaleString('id-ID',{maximumFractionDigits:1})+'%)'}}},
                onHover:(event,elements)=>{event.native.target.style.cursor=elements.length&&total?'pointer':'default';},
                onClick:(event,elements)=>{if(total&&elements.length)window.location.assign(payload.statusLinks[elements[0].index]);},
            },
        }));
    }
    const branchCanvas=document.getElementById('em-branch-chart');
    if(branchCanvas){
        instances.push(new Chart(branchCanvas,{
            type:'bar',data:{labels:Object.keys(series.branches).map(label=>'KC '+label),datasets:[{label:'Prospek',data:Object.values(series.branches),backgroundColor:['#307fe2','#6aa5eb','#8bc0f2','#a8d5f6'],hoverBackgroundColor:'#0857c3',borderRadius:5,barThickness:16,maxBarThickness:20}]},
            options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,animation:animation(),layout:{padding:{right:12}},
                plugins:{legend:{display:false},tooltip:{...tooltip,callbacks:{label:item=>number.format(item.raw)+' prospek'}}},
                scales:{x:{beginAtZero:true,border:{display:false},grid:{color:'#edf3fa',drawTicks:false},ticks:{precision:0,maxTicksLimit:5,color:'#8a9db1',font:{family:font,size:9}}},y:{border:{display:false},grid:{display:false},ticks:{color:'#547393',font:{family:font,size:10},padding:8}}},
                onHover:(event,elements)=>{event.native.target.style.cursor=elements.length?'pointer':'default';},
                onClick:(event,elements)=>{if(elements.length)window.location.assign(payload.branchLinks[elements[0].index]);},
            },
        }));
    }
    reduced.addEventListener('change',()=>instances.forEach(chart=>{chart.options.animation=animation();chart.update('none');}));
    window.addEventListener('pagehide',event=>{if(!event.persisted)instances.forEach(chart=>chart.destroy());});
})();
</script>
