<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>{{ $game->tr('title') }}</title>
<style>
    html, body { margin: 0; height: 100%; background: #000; overflow: hidden; }
    canvas { width: 100%; height: 100%; display: block; }
    #bar { position: fixed; left: 20%; right: 20%; top: 50%; height: 6px; background: #222; border-radius: 3px; }
    #fill { height: 100%; width: 0; background: linear-gradient(90deg,#22d3ee,#7c5cff); border-radius: 3px; transition: width .2s; }
</style>
</head>
<body>
<canvas id="unity-canvas" tabindex="-1"></canvas>
<div id="bar"><div id="fill"></div></div>
<script>
    var cfg = {!! json_encode($config, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!};
    var buildUrl = {!! json_encode($buildUrl, JSON_UNESCAPED_SLASHES) !!};
    var parentWin = window.parent;
    function tell(msg) { try { parentWin.postMessage(msg, '*'); } catch (e) {} }
    var instance = null;
    window.addEventListener('message', function (e) {
        if (e.source !== parentWin || !e.data || !instance) return;
        if (e.data.type === 'nebulo:mute' && instance.SendMessage && cfg.muteObject) {
            try { instance.SendMessage(cfg.muteObject, cfg.muteMethod || 'SetMuted', e.data.muted ? 1 : 0); } catch (err) {}
        }
    });
    var loader = document.createElement('script');
    loader.src = buildUrl + '/' + cfg.loader;
    loader.onerror = function () { tell({ type: 'nebulo:error', message: 'Unity loader failed to download.' }); };
    loader.onload = function () {
        createUnityInstance(document.getElementById('unity-canvas'), {
            dataUrl: buildUrl + '/' + cfg.data,
            frameworkUrl: buildUrl + '/' + cfg.framework,
            codeUrl: buildUrl + '/' + cfg.code,
            streamingAssetsUrl: buildUrl + '/StreamingAssets',
            companyName: cfg.company || '',
            productName: cfg.product || '',
            productVersion: cfg.version || '1.0',
        }, function (p) { document.getElementById('fill').style.width = Math.round(p * 100) + '%'; })
        .then(function (inst) { instance = inst; document.getElementById('bar').remove(); tell({ type: 'nebulo:ready' }); })
        .catch(function (err) { tell({ type: 'nebulo:error', message: 'This Unity game could not start on your device.' }); });
    };
    document.body.appendChild(loader);
</script>
</body>
</html>
