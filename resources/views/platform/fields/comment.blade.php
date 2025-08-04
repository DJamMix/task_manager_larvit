@vite('resources/css/custom-styles.css')

<div class="comment-box {{ $borderColor }}">
    <div class="comment-header">
        <span class="comment-author">{{ $author }}</span>
        <span class="comment-date">{{ $date }}</span>
    </div>
    <div class="comment-text">
        {!! $text !!}
    </div>
</div>