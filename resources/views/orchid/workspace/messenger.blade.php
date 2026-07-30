@extends('platform::app')

@section('body')
    <div class="p-0 h-100 bx-messenger-workspace-wrap">
        <div class="workspace mb-0 p-0 d-flex flex-column h-100 overflow-hidden">
            @yield('workspace')
        </div>
    </div>
@endsection
