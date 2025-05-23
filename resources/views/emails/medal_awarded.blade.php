@extends('layouts.mail')

@section('content')
<p>Hi {{ $user->name }},</p>
<p>Congratulations! You have earned a new medal:</p>
<p>
  @if($medal->image_path)
    <img src="{{ Storage::disk('medals')->url(str_replace('.svg', '.png', $medal->image_path)) }}" alt="{{ $medal->name }}" style="height:64px;vertical-align:middle;margin-right:12px;border-radius:8px;background:#fff;box-shadow:0 2px 8px #eee;" />
  @endif
  <strong>{{ $medal->name }}</strong>
</p>
@if($medal->description)
<p>{{ $medal->description }}</p>
@endif
<p>To view your medals, visit your <a href="{{ config('app.url') }}/profile/{{ $user->id }}">profile</a>.</p>
<p>Keep up the great work!</p>
@endsection
