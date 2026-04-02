<?php

test('service worker does not set notification tag (prevents overwriting)', function (): void {
    $swPath = realpath(__DIR__.'/../../public/sw.js');
    expect(file_exists($swPath))->toBeTrue();

    $swContents = file_get_contents($swPath);
    expect(is_string($swContents))->toBeTrue();
    expect($swContents)->not->toMatch('/\btag\s*:/');

    $pushSwPath = realpath(__DIR__.'/../../public/push-sw.js');
    expect(file_exists($pushSwPath))->toBeTrue();

    $pushSwContents = file_get_contents($pushSwPath);
    expect(is_string($pushSwContents))->toBeTrue();
    expect($pushSwContents)->not->toMatch('/\btag\s*:/');
});
