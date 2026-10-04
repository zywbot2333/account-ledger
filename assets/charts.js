/**
 * 轻量 SVG 分组柱状图（收入 vs 支出）
 * 用法: renderBars(document.getElementById('chart'), {
 *   labels: ['10-01', ...],
 *   series: [{ name: '收入', color: '#16a34a', data: [...] }, { name: '支出', color: '#ef4444', data: [...] }]
 * })
 */
function renderBars(el, opts) {
  if (!el) return;
  var labels = opts.labels || [];
  var series = opts.series || [];
  var W = 760, H = 320, padL = 58, padR = 12, padT = 14, padB = 32;
  var iw = W - padL - padR, ih = H - padT - padB;
  var SVGNS = 'http://www.w3.org/2000/svg';

  var maxVal = 1;
  series.forEach(function (s) {
    s.data.forEach(function (v) { if (v > maxVal) maxVal = v; });
  });

  // 取一个"好看"的纵轴上限（1/2/5×10^n）
  var nice = Math.pow(10, Math.floor(Math.log10(maxVal)));
  var r = maxVal / nice;
  if (r > 5) nice *= 10;
  else if (r > 2) nice *= 5;
  else if (r > 1) nice *= 2;
  var max = nice;

  function y(v) { return padT + ih - (v / max) * ih; }
  function fmtAxis(v) {
    if (v >= 10000) return (v / 10000).toFixed(v % 10000 ? 1 : 0) + '万';
    if (v >= 1000) return (v / 1000).toFixed(v % 1000 ? 1 : 0) + 'k';
    return String(Math.round(v));
  }
  function fmtMoney(v) {
    return v.toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  function mk(tag, attrs) {
    var node = document.createElementNS(SVGNS, tag);
    for (var k in attrs) node.setAttribute(k, attrs[k]);
    return node;
  }

  var svg = mk('svg', { viewBox: '0 0 ' + W + ' ' + H, role: 'img' });
  svg.classList.add('chart');

  // 网格与纵轴刻度
  var steps = 4;
  for (var i = 0; i <= steps; i++) {
    var v = max * i / steps;
    var yy = y(v);
    svg.appendChild(mk('line', {
      x1: padL, x2: W - padR, y1: yy, y2: yy,
      'class': 'grid-line' + (i === 0 ? ' axis' : '')
    }));
    var t = mk('text', { x: padL - 8, y: yy + 4, 'class': 'axis-label', 'text-anchor': 'end' });
    t.textContent = fmtAxis(v);
    svg.appendChild(t);
  }

  var n = labels.length;
  var slot = iw / Math.max(n, 1);
  var groupW = Math.min(slot * 0.72, 64);
  var barW = groupW / series.length;
  var step = Math.ceil(n / 16);

  labels.forEach(function (lab, i) {
    var cx = padL + slot * i + slot / 2;
    series.forEach(function (s, si) {
      var v = s.data[i] || 0;
      var bar = mk('rect', {
        x: cx - groupW / 2 + barW * si,
        y: y(v),
        width: Math.max(barW - 2, 1),
        height: Math.max(y(0) - y(v), 0),
        rx: Math.min(4, barW / 3),
        fill: s.color,
        'class': 'bar'
      });
      var title = mk('title', {});
      title.textContent = lab + ' · ' + s.name + ' ¥' + fmtMoney(v);
      bar.appendChild(title);
      svg.appendChild(bar);
    });
    if (n <= 16 || i % step === 0) {
      var lt = mk('text', { x: cx, y: H - 10, 'class': 'axis-label', 'text-anchor': 'middle' });
      lt.textContent = lab;
      svg.appendChild(lt);
    }
  });

  el.innerHTML = '';
  el.appendChild(svg);
  requestAnimationFrame(function () {
    requestAnimationFrame(function () { svg.classList.add('animate'); });
  });
}
