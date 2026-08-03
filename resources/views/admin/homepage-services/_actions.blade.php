<div class="d-flex gap-2">
    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.homepage-services.edit', $service) }}">Edit</a>
    <form method="POST" action="{{ route('admin.homepage-services.destroy', $service) }}">
        @csrf
        @method('DELETE')
        <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
    </form>
</div>
