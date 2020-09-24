<!-- Filename Field -->
<div class="form-group col-sm-6">
    {!! Form::label('filename', __('models/translationFiles.fields.filename').':') !!}
    {!! Form::text('filename', null, ['class' => 'form-control']) !!}
</div>

<!-- Submit Field -->
<div class="form-group col-sm-12">
    {!! Form::submit(__('crud.save'), ['class' => 'btn btn-primary']) !!}
    <a href="{{ route('translationFiles.index') }}" class="btn btn-default">@lang('crud.cancel')</a>
</div>