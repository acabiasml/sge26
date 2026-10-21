<div class="modal fade" id="confirmActionModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="confirmActionTitle" aria-describedby="confirmActionMessage">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="confirmActionTitle">{{ __('Confirme a ação') }}</h2>
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('Fechar') }}"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p id="confirmActionMessage" class="mb-0"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">{{ __('Cancelar') }}</button>
                <button type="button" class="btn btn-primary" data-confirm-accept>{{ __('Confirmar') }}</button>
            </div>
        </div>
    </div>
</div>
