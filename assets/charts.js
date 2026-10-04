/**
 * 轻量 SVG 分组柱状图 v2（收入 vs 支出）
 * 特性：渐变圆角柱、悬停高亮、均值参考线、交错生长动画、title 悬浮数值
 * 用法: renderBars(document.getElementById('chart'), {
 *   labels: ['10-01', ...],
 *   series: [{ name: '收入', color: '#10b981', data: [...] }, { name: '支出', color: '#f43f5e', data: [...] }]
 * })
 */
function renderBars(el, opts) {
  if (!el) return;
  var labels = opts.labels || [];
  var series = opts.series || [];
  var W = 760, H = 330, padL = 58, padR = 16, padT = 18, padB = 32;
  var iw = W - padL - padR, ih = H - padT - padB;
  var SVGNS = 'http://www.w3.org/2000/svg';

  var maxVal = 1;
  series.forEach(function (s) {
    s.data.forEach(function (v) { if (v > maxVal) maxVal = v; });
  });

  // 取一个"好看"的纵轴上限（1/2/5×10^n）
  var nice = Math.pow(10, Math.floor(Math.log10(maxVal)));
  var r0 = maxVal / nice;
  if (r0 > 5) nice *= 10;
  else if (r0 > 2) nice *= 5;
  else if (r0 > 1) nice *= 2;
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
  function txt(x, yv, str, cls, anchor, fill, opacity) {
    var t = mk('text', { x: x, y: yv, 'class': cls, 'text-anchor': anchor || 'middle' });
    if (fill) t.setAttribute('fill', fill);
    if (opacity != null) t.setAttribute('opacity', opacity);
    t.textContent = str;
    return t;
  }

  var svg = mk('svg', { viewBox: '0 0 ' + W + ' ' + H, role: 'img' });
  svg.classList.add('chart');

  // 渐变定义（柱体自上而下渐隐，观感更轻盈）
  var defs = mk('defs', {});
  series.forEach(function (s, si) {
    var g = mk('linearGradient', { id: 'bar-grad-' + si, x1: 0, y1: 0, x2: 0, y2: 1 });
    g.appendChild(mk('stop', { offset: '0%', 'stop-color': s.color, 'stop-opacity': 1 }));
    g.appendChild(mk('stop', { offset: '100%', 'stop-color': s.color, 'stop-opacity': 0.45 }));
    defs.appendChild(g);
  });
  svg.appendChild(defs);

  // 网格与纵轴刻度
  var steps = 4;
  for (var i = 0; i <= steps; i++) {
    var v = max * i / steps;
    var yy = y(v);
    svg.appendChild(mk('line', {
      x1: padL, x2: W - padR, y1: yy, y2: yy,
      'class': 'grid-line' + (i === 0 ? ' axis' : '')
    }));
    svg.appendChild((function () {
      var t = txt(padL - 8, yy + 4, fmtAxis(v), 'axis-label', 'end');
      return t;
    })());
  }

  var n = labels.length;
  var slot = iw / Math.max(n, 1);
  var groupW = Math.min(slot * 0.72, 64);
  var barW = groupW / series.length;
  var step = Math.ceil(n / 16);
  var yBase = y(0);

  labels.forEach(function (lab, i) {
    var cx = padL + slot * i + slot / 2;
    series.forEach(function (s, si) {
      var val = s.data[i] || 0;
      if (val <= 0) return;
      var h = (val / max) * ih;
      var x = cx - groupW / 2 + barW * si;
      var w = Math.max(barW - 2, 1);
      var rr = Math.min(Math.max(w * 0.32, 1.5), Math.max(h, 0), 6);
      var yTop = yBase - h;

      // 顶部圆角柱
      var d = 'M' + x + ',' + yBase +
              ' L' + x + ',' + (yTop + rr) +
              ' Q' + x + ',' + yTop + ' ' + (x + rr) + ',' + yTop +
              ' L' + (x + w - rr) + ',' + yTop +
              ' Q' + (x + w) + ',' + yTop + ' ' + (x + w) + ',' + (yTop + rr) +
              ' L' + (x + w) + ',' + yBase + ' Z';
      var bar = mk('path', { d: d, fill: 'url(#bar-grad-' + si + ')', 'class': 'bar' });
      bar.style.transitionDelay = Math.min(i * 16, 480) + 'ms';

      var title = mk('title', {});
      title.textContent = lab + ' · ' + s.name + ' ¥' + fmtMoney(val);
      bar.appendChild(title);
      svg.appendChild(bar);
    });
    if (n <= 16 || i % step === 0) {
      svg.appendChild(txt(cx, H - 10, lab, 'axis-label'));
    }
  });

  // 均值参考线（虚线 + 右侧标注）
  series.forEach(function (s, si) {
    var sum = 0;
    s.data.forEach(function (v) { sum += v; });
    var avg = n ? sum / n : 0;
    if (avg <= 0 || avg >= max) return;
    var ay = y(avg);
    svg.appendChild(mk('line', {
      x1: padL, x2: W - padR, y1: ay, y2: ay,
      stroke: s.color, 'class': 'avg-line'
    }));
    svg.appendChild(txt(W - padR, ay - 5, '均 ' + fmtAxis(avg), 'avg-label', 'end', s.color, 0.8));
  });

  el.innerHTML = '';
  el.appendChild(svg);
  requestAnimationFrame(function () {
    requestAnimationFrame(function () { svg.classList.add('animate'); });
  });
}
