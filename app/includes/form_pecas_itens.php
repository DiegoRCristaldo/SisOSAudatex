<div class="card shadow-sm">
    <div class="card mb-4">
        <div class="card-header bg-warning">
            <h5 class="mb-0"><i class="bi bi-pencil"></i> Peças Manuais</h5>
        </div>
        <div class="card-body">
            <textarea name="pecas_manuais" class="form-control" rows="3" 
                        placeholder="Caso não encontre as peças em Peças/Itens para Orçamento"
                        id="pecas-manuais"><?= htmlspecialchars($_POST['pecas_manuais'] ?? '') ?></textarea>
            <button type="button" class="btn btn-warning mt-2" id="adicionar-manuais">
                <i class="bi bi-plus-circle"></i> Adicionar à Lista
            </button>
        </div>
    </div>
    <div class="card-header bg-success text-white">
        <h5 class="mb-0"><i class="bi bi-list-check"></i> Peças/Itens para Orçamento</h5>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> Selecione peças da lista OU informe manualmente.
        </div>
        
        <div class="row mb-4">
            <div class="col-md-6">
                <label class="form-label">Filtrar por Categoria</label>
                <select class="form-select" id="filtro-categoria">
                    <option value="">Todas as categorias</option>
                    <?php foreach (array_keys($pecas) as $categoria): ?>
                        <option value="<?= $categoria ?>"><?= ucfirst($categoria) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Buscar Peça</label>
                <input type="text" class="form-control" id="buscar-peca" placeholder="Digite o nome da peça...">
            </div>
        </div>
        
        <div class="row" id="lista-pecas">
            <?php if (!empty($pecas)): ?>
                <?php foreach ($pecas as $categoria => $itens_categoria): ?>
                <div class="col-12 mb-4">
                    <h6 class="border-bottom pb-2">
                        <i class="bi bi-folder"></i> <?= ucfirst($categoria) ?>
                        <small class="text-muted">(<?= count($itens_categoria) ?> itens)</small>
                    </h6>
                    <div class="row">
                        <?php foreach ($itens_categoria as $peca): ?>
                        <div class="col-md-4 mb-3 categoria-<?= $categoria ?>">
                            <div class="card peca-card h-100">
                                <div class="card-body">
                                    <div class="form-check">
                                        <input class="form-check-input peca-checkbox" type="checkbox" 
                                               name="itens[]" value="<?= $peca['id'] ?>" 
                                               id="peca_<?= $peca['id'] ?>"
                                               data-categoria="<?= $categoria ?>"
                                               data-nome="<?= htmlspecialchars($peca['nome']) ?>">
                                        <label class="form-check-label w-100" for="peca_<?= $peca['id'] ?>">
                                            <strong><?= htmlspecialchars($peca['nome']) ?></strong>
                                            <?php if ($peca['codigo_referencia']): ?>
                                            <br><small class="text-muted">Código: <?= $peca['codigo_referencia'] ?></small>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-3">
                    <i class="bi bi-tools fs-1 text-muted"></i>
                    <p class="text-muted">Nenhuma peça cadastrada</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="card" id="selecionadas-container">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0"><i class="bi bi-check-circle"></i> Peças Selecionadas</h6>
            </div>
            <div class="card-body">
                <div id="nenhuma-peca-mensagem">
                    <p class="text-muted mb-0">Nenhuma peça selecionada</p>
                </div>
                <ul class="list-group" id="lista-selecionadas"></ul>
                <button type="button" class="btn btn-sm btn-outline-danger mt-3" id="limpar-todas">
                    <i class="bi bi-trash"></i> Limpar todas
                </button>
            </div>
        </div>
        
        <div class="mt-4 d-flex justify-content-between">
            <button type="button" class="btn btn-secondary prev-tab" data-prev="dados">
                <i class="bi bi-arrow-left"></i> Voltar
            </button>
            <button type="button" class="btn btn-primary next-tab" data-next="fotos" id="btn-proximo-fotos" disabled>
                <i class="bi bi-arrow-right"></i> Próximo: Fotos
            </button>
        </div>
    </div>
</div>