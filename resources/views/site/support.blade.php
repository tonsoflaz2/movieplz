@extends('site.layout')
@section('title', 'Support')
@section('content')
<h1>MoviePlz support</h1>
<p>MoviePlz is a private way for friends to suggest movies and shows to each other.</p>

<h2>Get MoviePlz</h2>
{{-- Plain links, so search engines can crawl from this page to both store listings. --}}
<ul>
    <li><a href="https://apps.apple.com/us/app/movieplz/id6762552708">MoviePlz on the App Store</a> (iPhone)</li>
    <li><a href="https://play.google.com/store/apps/details?id=com.hypeapps.movieplz">MoviePlz on Google Play</a> (Android)</li>
</ul>

<h2>Getting started</h2>
<ul>
    <li><strong>Starting a list:</strong> open MoviePlz, tap <em>Set up as host</em> and type your name. Then add what you've seen from a Google Sheet or by pasting titles.</li>
    <li><strong>Joining a friend's list:</strong> tap <em>Join as a friend</em> and enter the five-digit code they read you. Codes work once and expire after ten minutes, so ask for a fresh one if it doesn't work.</li>
</ul>

<h2>Using a Google Sheet</h2>
<ul>
    <li>Put <em>Title</em> in the first row (<em>Type</em> and <em>Year</em> are optional: type is film or series).</li>
    <li>In Google Sheets, choose <em>Share</em>, set General access to <em>Anyone with the link</em> (Viewer), and copy the link. You don't need to publish it to the web.</li>
    <li>Paste the link in MoviePlz under <em>Add titles</em>. New rows show up within a few minutes.</li>
</ul>

<h2>Suggestions</h2>
<p>Friends suggest a title; the host answers <em>I'll watch it</em> (it goes on their Up next list), <em>Saw it!</em> (it joins their list) or <em>Not for me</em>. Friends get a notification as it moves along.</p>

<h2>Leaving or deleting</h2>
<p>Friends can tap <em>Leave this library</em> at the bottom of their home screen; hosts can tap <em>Delete my library</em> on their dashboard. See the <a href="/privacy">privacy policy</a> for what's deleted.</p>

<h2>Contact</h2>
@if (config('movieplz.support_email'))
    <p>Email <a href="mailto:{{ config('movieplz.support_email') }}">{{ config('movieplz.support_email') }}</a> and we'll get back to you.</p>
@else
    <p class="muted">Contact details coming soon.</p>
@endif
@endsection
