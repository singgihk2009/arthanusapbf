<?php

it('redirects root to login', function () {
    $response = $this->get('/');

    $response->assertRedirect('/login');
});
