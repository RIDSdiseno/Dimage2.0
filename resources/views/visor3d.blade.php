<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no">
    <meta name="theme-color" content="#000000" />
    <link rel="manifest" href="/visor3d/manifest.json" />
    <link rel="shortcut icon" href="/visor3d/favicon.ico" />
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/css/bootstrap.min.css"
        integrity="sha384-GJzZqFGwb1QTTN6wy59ffF1BuGJpLSa9DkKMp0DgiMDm4iYMj70gZWKYbI706tWS" crossorigin="anonymous">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.0/css/all.css"
        integrity="sha384-lZN37f5QGtY3VHgisS14W3ExzMWZxybE1SJSEsQp9S+oqd12jhcu+A56Ebc1zFSJ" crossorigin="anonymous">
    <style>
        body { background-color: #000 !important; color: #fff }
    </style>
    <title>Visor DICOM - Dimage</title>
    <link href="/visor3d/static/css/2.16f47b55.chunk.css" rel="stylesheet">
    <link href="/visor3d/static/css/main.befffb1c.chunk.css" rel="stylesheet">
</head>
<body>
    <noscript>Necesita tener javascript habilitado para utilizar este visor.</noscript>
    <div id="root"></div>
    @if(isset($fileUrl))
    <script>
    (function () {
        // Set ?file= in the URL so med3web reads it from window.location.search.
        var fileUrl = @json($fileUrl);
        var name    = @json($name ?? '');
        history.replaceState(null, '',
            '?file=' + encodeURIComponent(fileUrl) +
            (name ? '&name=' + encodeURIComponent(name) : ''));

        // Pre-signed S3 URLs for every DCM slice, keyed by the proxy URL that
        // med3web would normally construct.  When the map is populated the XHR
        // interceptor below rewrites each request to go directly to S3 — no PHP
        // hop, fully parallel, eliminates the single-thread bottleneck.
        var urlMap = @json($urlMap ?? (object)[]);
        if (Object.keys(urlMap).length > 0) {
            var _open = XMLHttpRequest.prototype.open;
            XMLHttpRequest.prototype.open = function (method, url) {
                var args = Array.prototype.slice.call(arguments);
                if (urlMap[url]) args[1] = urlMap[url];
                return _open.apply(this, args);
            };
        }
    })();
    </script>
    @endif
    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"
        integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo"
        crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.6/umd/popper.min.js"
        integrity="sha384-wHAiFfRlMFy6i5SRaxvfOCifBUQy1xHdJ/yoi7FRNXMRBu5WHdZYu1hA6ZOblgut"
        crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/js/bootstrap.min.js"
        integrity="sha384-B0UglyR+jN6CkvvICOB2joaf5I4l3gm9GU6Hc1og6Ls7i6U/mkkaduKaBhlAXv9k"
        crossorigin="anonymous"></script>
    <script>!function (e) { function r(r) { for (var n, l, i = r[0], f = r[1], a = r[2], c = 0, s = []; c < i.length; c++)l = i[c], Object.prototype.hasOwnProperty.call(o, l) && o[l] && s.push(o[l][0]), o[l] = 0; for (n in f) Object.prototype.hasOwnProperty.call(f, n) && (e[n] = f[n]); for (p && p(r); s.length;)s.shift()(); return u.push.apply(u, a || []), t() } function t() { for (var e, r = 0; r < u.length; r++) { for (var t = u[r], n = !0, i = 1; i < t.length; i++) { var f = t[i]; 0 !== o[f] && (n = !1) } n && (u.splice(r--, 1), e = l(l.s = t[0])) } return e } var n = {}, o = { 1: 0 }, u = []; function l(r) { if (n[r]) return n[r].exports; var t = n[r] = { i: r, l: !1, exports: {} }; return e[r].call(t.exports, t, t.exports, l), t.l = !0, t.exports } l.m = e, l.c = n, l.d = function (e, r, t) { l.o(e, r) || Object.defineProperty(e, r, { enumerable: !0, get: t }) }, l.r = function (e) { "undefined" != typeof Symbol && Symbol.toStringTag && Object.defineProperty(e, Symbol.toStringTag, { value: "Module" }), Object.defineProperty(e, "__esModule", { value: !0 }) }, l.t = function (e, r) { if (1 & r && (e = l(e)), 8 & r) return e; if (4 & r && "object" == typeof e && e && e.__esModule) return e; var t = Object.create(null); if (l.r(t), Object.defineProperty(t, "default", { enumerable: !0, value: e }), 2 & r && "string" != typeof e) for (var n in e) l.d(t, n, function (r) { return e[r] }.bind(null, n)); return t }, l.n = function (e) { var r = e && e.__esModule ? function () { return e.default } : function () { return e }; return l.d(r, "a", r), r }, l.o = function (e, r) { return Object.prototype.hasOwnProperty.call(e, r) }, l.p = "/visor3d/"; var i = this.webpackJsonpmed3web = this.webpackJsonpmed3web || [], f = i.push.bind(i); i.push = r, i = i.slice(); for (var a = 0; a < i.length; a++)r(i[a]); var p = f; t() }([])</script>
    <script src="/visor3d/static/js/2.8a2780fa.chunk.js"></script>
    <script src="/visor3d/static/js/main.88cbe2d5.chunk.js"></script>
</body>
</html>
