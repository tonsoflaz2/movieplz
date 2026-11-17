@extends('site.layout')
@section('title', 'Privacy Policy')
@section('content')
<h1>Privacy Policy</h1>
<p class="muted">Last updated {{ \Illuminate\Support\Carbon::parse('2026-11-07')->format('F j, Y') }}</p>

<p>MoviePlz lets friends suggest movies and shows to each other. It has no accounts or passwords, shows no ads, and does not track you.</p>

<h2>What we keep</h2>
<ul>
    <li><strong>The name you type</strong> when you start a list or join one, shown to the people in that list.</li>
    <li><strong>Your list</strong>: the titles you add, by hand or from a Google Sheet you link.</li>
    <li><strong>Suggestions</strong>: the titles, years and notes friends send, what happened to them (in progress, added, declined), and any reply the host writes back.</li>
    <li><strong>A notification token</strong> for your device, if you allow notifications, so we can tell you about suggestions.</li>
    <li><strong>Your theme</strong>: the colors you pick for the app, if you change them.</li>
    <li><strong>A session cookie</strong> that keeps you signed in to your list on this device.</li>
    <li>Our server's standard <strong>logs</strong> (including IP addresses), kept for a short time to run and protect the service.</li>
</ul>

<h2>How it's used</h2>
<p>Only to run the app: showing lists and suggestions to the people in them, and sending the notifications you'd expect. We don't sell or share your information, use it for advertising, or combine it with data from elsewhere.</p>

<h2>Services we use</h2>
<ul>
    <li><strong>The Movie Database (TMDB)</strong>: we send it titles (never your name) to fetch posters and descriptions. This product uses the TMDB API but is not endorsed or certified by TMDB.</li>
    <li><strong>Google Sheets</strong>: if you link a sheet, we read it to keep your list in sync.</li>
    <li><strong>Apple Push Notification service</strong> (iPhone and iPad) and <strong>Google Firebase Cloud Messaging</strong> (Android): deliver notifications to your device. They receive your device's notification token and the notification itself, and only if you allow notifications.</li>
</ul>

<h2>Deleting your information</h2>
<p>In the app, friends can tap <em>Leave this library</em> and hosts can tap <em>Delete my library</em>. Everything for that person or list is deleted straight away. A host can also remove a friend's access at any time.@if (config('movieplz.support_email')) For anything else, email <a href="mailto:{{ config('movieplz.support_email') }}">{{ config('movieplz.support_email') }}</a>.@endif</p>

<h2>Children</h2>
<p>MoviePlz is not directed at children under 13, and we don't knowingly collect information from them.</p>

<h2>Changes</h2>
<p>If this policy changes, we'll update this page and the date above.</p>
@endsection
