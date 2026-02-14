<div class="card shadow-sm">
    <div class="card-header bg-danger text-white">
        <h5 class="mb-0"><i class="bi bi-check-circle"></i> Revisão da OS</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Dados do Veículo</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr><th>Placa:</th><td id="revisao-placa">-</td></tr>
                            <tr><th>EB:</th><td id="revisao-eb">-</td></tr>
                            <tr><th>Chassi:</th><td id="revisao-chassi">-</td></tr>
                            <tr><th>Marca/Modelo:</th><td id="revisao-marca-modelo">-</td></tr>
                            <tr><th>Ano/Cor:</th><td id="revisao-ano-cor">-</td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Peças Selecionadas</h6>
                    </div>
                    <div class="card-body">
                        <div id="revisao-pecas">
                            <p class="text-muted mb-0">Carregando...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="bi bi-images"></i> Fotos</h6>
                    </div>
                    <div class="card-body">
                        <div id="revisao-fotos">
                            <p class="text-muted mb-0">Carregando...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="alert alert-success mt-4">
            <i class="bi bi-info-circle"></i>
            <strong>Pronto para enviar!</strong> Após o envio, nossa equipe Audatex 
            irá analisar sua solicitação e fornecer uma cotação detalhada.
        </div>
        
        <div class="mt-4 d-flex justify-content-between">
            <button type="button" class="btn btn-secondary prev-tab" data-prev="fotos">
                <i class="bi bi-arrow-left"></i> Voltar
            </button>
            <button type="submit" class="btn btn-success btn-lg" id="btn-enviar-os">
                <i class="bi bi-check-circle"></i> Enviar para Cotação
            </button>
        </div>
    </div>
</div>