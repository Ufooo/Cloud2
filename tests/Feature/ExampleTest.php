<?php

test('unauthenticated users are redirected to login', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});
