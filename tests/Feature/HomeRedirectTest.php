<?php

it('redirects the root URL to the dashboard', function () {
    $this->get('/')->assertRedirect('/dashboard');
});
