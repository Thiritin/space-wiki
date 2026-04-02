<?php

test('guests are redirected to the login page', function () {
    $response = $this->get('/');

    $response->assertRedirect('/login');
});

test('login page renders successfully', function () {
    $response = $this->get('/login');

    $response->assertSuccessful();
});
