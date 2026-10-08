<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>{{ $game->tr('title') }}</title>
<style>
    html, body { margin: 0; height: 100%; background: #000; overflow: hidden; }
    #stage { position: fixed; inset: 0; }
    #stage ruffle-player, #stage ruffle-object { width: 100%; height: 100%; display: block; }
    #fallback { display: none; position: fixed; inset: 0; color: #fff; font: 15px system-ui, sans-serif; align-items: center; justify-content: center; text-align: center; padding: 24px; }
</style>
</head>
<body>
<div id="stage"></div>
<div id="fallback">Your browser could not run the Flash emulator for this game.</div>
<script>
    // Self-hosted Ruffle. No Adobe Flash Player is required or used.
    window.RufflePlayer = window.RufflePlayer || {};
    window.RufflePlayer.config = {
        publicPath: {!! json_encode(dirname($ruffleUrl).'/', JSON_UNESCAPED_SLASHES) !!},
        autoplay: 'on',
        unmuteOverlay: 'hidden',
        splashScreen: false,
        contextMenu: 'rightClickOnly',
        showSwfDownload: false,
        allowScriptAccess: false,
        allowNetworking: 'none',
        upgradeToHttps: true,
        letterbox: 'on',
        openUrlMode: 'deny',
        warnOnUnsupportedContent: true,
        logLevel: 'error',
    };
    var parentWin = window.parent;
    function tell(msg) { try { parentWin.postMessage(msg, '*'); } catch (e) {} }
    var player = null;
    window.addEventListener('message', function (e) {
        if (e.source !== parentWin || !e.data || typeof e.data !== 'object') return;
        if (!player) return;
        if (e.data.type === 'nebulo:mute') { player.volume = e.data.muted ? 0 : 1; }
        if (e.data.type === 'nebulo:pause' && player.pause) { player.pause(); }
        if (e.data.type === 'nebulo:resume' && player.play) { player.play(); }
    });
    function start() {
        if (!window.RufflePlayer.newest) { document.getElementById('fallback').style.display = 'flex'; tell({ type: 'nebulo:error', message: 'Flash emulator unavailable.' }); return; }
        var ruffle = window.RufflePlayer.newest();
        player = ruffle.createPlayer();
        document.getElementById('stage').appendChild(player);
        player.addEventListener('loadedmetadata', function () { tell({ type: 'nebulo:ready' }); });
        player.addEventListener('loadeddata', function () { tell({ type: 'nebulo:ready' }); });
        var p = player.ruffle ? player.ruffle().load({ url: {!! json_encode($swfUrl, JSON_UNESCAPED_SLASHES) !!} }) : player.load({ url: {!! json_encode($swfUrl, JSON_UNESCAPED_SLASHES) !!} });
        if (p && p.catch) p.catch(function (err) { tell({ type: 'nebulo:error', message: 'This Flash game could not be loaded.' }); });
    }
</script>
<script src="{{ $ruffleUrl }}" onload="start()" onerror="tell({type:'nebulo:error',message:'Flash emulator failed to download.'})"></script>
</body>
</html>
