<div class="d-flex gap-2">
    <a class="btn text-primary" href="{{ route('admin.homepage-services.edit', $service) }}"><i class="ti ti-edit fs-6"></i></a>
    <form method="POST" action="{{ route('admin.homepage-services.destroy', $service) }}">
        @csrf
        @method('DELETE')
        <button class="btn text-danger" type="submit"><i class="ti ti-trash fs-6"></i></button>
    </form>
</div>
