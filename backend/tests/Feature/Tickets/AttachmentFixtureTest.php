<?php

it('correctly detects MIME types for attachment fixtures', function () {
    $screen = fixtureUpload('screen.png');
    expect($screen->getMimeType())->toBe('image/png');

    $photo = fixtureUpload('photo.png');
    expect(str_starts_with($photo->getMimeType(), 'image/'))->toBeFalse();
});

