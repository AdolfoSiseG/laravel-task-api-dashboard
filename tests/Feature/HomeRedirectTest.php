<?php

it('redirects the root URL to the dashboard', function () {
    $this->get('/')->assertRedirect('/dashboard');
});

it('exposes a public health check', function () {
    $this->get('/health')->assertOk()->assertExactJson(['status' => 'ok']);
});
