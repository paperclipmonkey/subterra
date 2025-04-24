@extends('layouts.mail')

@section('content')
<p>Hi {{ $user->name }},</p>
<p>You have been tagged in a new trip by {{ $creator->name }}.</p>
<p><strong>Trip:</strong> {{ $trip->name }}</p>
<p><strong>Description:</strong> {{ $trip->description }}</p>
<p><strong>Start Time:</strong> {{ $trip->start_time }}</p>
<p><strong>End Time:</strong> {{ $trip->end_time }}</p>
<p>Log in to view more details.</p>
@endsection
