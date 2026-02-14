<div class="card shadow-sm">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0"><i class="bi bi-images"></i> Fotos dos Danos (Opcional)</h5>
    </div>
    <div class="card-body">
        <div class="upload-area" id="upload-area">
            <i class="bi bi-cloud-upload fs-1 text-muted mb-3"></i>
            <p class="mb-2"><strong>Clique para selecionar fotos</strong></p>
            <p class="small text-muted mb-3">Formatos: JPG, PNG, GIF (Máx: 10MB cada)</p>
            <button type="button" class="btn btn-primary btn-lg" id="btn-selecionar-fotos">
                <i class="bi bi-folder2-open"></i> Selecionar Fotos
            </button>
            <input type="file" name="fotos[]" id="input-fotos" 
                   class="form-control mt-3" multiple accept="image/*" style="display:none;">
        </div>
        
        <div id="preview-container" class="mt-3 row"></div>
        <div id="upload-status" class="mt-2"></div>
        
        <div class="mt-4 d-flex justify-content-between">
            <button type="button" class="btn btn-secondary prev-tab" data-prev="itens">
                <i class="bi bi-arrow-left"></i> Voltar
            </button>
            <button type="button" class="btn btn-primary next-tab" data-next="revisao">
                <i class="bi bi-arrow-right"></i> Próximo: Revisão
            </button>
        </div>
    </div>
</div>