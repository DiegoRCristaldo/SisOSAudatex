<div class="card shadow-sm">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="bi bi-car-front-fill"></i> Dados do Veículo</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Placa *</label>
                <input type="text" name="placa" class="form-control" 
                       value="<?= htmlspecialchars($_POST['placa'] ?? '') ?>" 
                       required oninput="this.value = this.value.toUpperCase()">
                <small class="form-text text-muted">Ex: ABC1D23 ou ABC1234</small>
            </div>
            <div class="col-md-3">
                <label class="form-label">EB *</label>
                <input type="text" name="eb" class="form-control" 
                       value="<?= htmlspecialchars($_POST['eb'] ?? '') ?>" 
                       required oninput="this.value = this.value.toUpperCase()">
            </div>
            <div class="col-md-3">
                <label class="form-label">Chassi *</label>
                <input type="text" name="chassi" class="form-control" 
                       value="<?= htmlspecialchars($_POST['chassi'] ?? '') ?>" 
                       required oninput="this.value = this.value.toUpperCase()">
            </div>
            <div class="col-md-3">
                <label class="form-label">Marca *</label>
                <select name="marca" class="form-select" required id="select-marca">
                    <option value="">Selecione...</option>
                    <?php
                    $marcas = ['Chevrolet', 'Volkswagen', 'Fiat', 'Ford', 'Toyota', 'Hyundai', 'Renault', 'Honda', 'Nissan', 'Jeep', 'BMW', 'Mercedes-Benz', 'Audi', 'Volvo'];
                    foreach ($marcas as $marca) {
                        $selected = ($_POST['marca'] ?? '') === $marca ? 'selected' : '';
                        echo "<option value='$marca' $selected>$marca</option>";
                    }
                    ?>
                    <option value="outra" <?= ($_POST['marca'] ?? '') === 'outra' ? 'selected' : '' ?>>Outra...</option>
                </select>
                <input type="text" name="marca_outra" class="form-control mt-2" 
                       id="input-marca-outra" placeholder="Informe a marca"
                       value="<?= htmlspecialchars($_POST['marca_outra'] ?? '') ?>"
                       style="display: <?= ($_POST['marca'] ?? '') === 'outra' ? 'block' : 'none' ?>;">
            </div>
            <div class="col-md-3">
                <label class="form-label">Modelo *</label>
                <input type="text" name="modelo" class="form-control" 
                       value="<?= htmlspecialchars($_POST['modelo'] ?? '') ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Ano *</label>
                <select name="ano" class="form-select" required>
                    <option value="">Selecione...</option>
                    <?php for ($ano = date('Y'); $ano >= 1980; $ano--): ?>
                        <option value="<?= $ano ?>" <?= ($_POST['ano'] ?? '') == $ano ? 'selected' : '' ?>><?= $ano ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Cor</label>
                <input type="text" name="cor" class="form-control" 
                       value="<?= htmlspecialchars($_POST['cor'] ?? '') ?>">
            </div>
            <?php if ($_SESSION['usuario_tipo'] === 'admin' || $_SESSION['usuario_tipo'] === 'audatex'): ?>
            <div class="col-md-3">
                <label class="form-label">Prioridade</label>
                <select name="prioridade" class="form-select">
                    <?php $prioridade = $_POST['prioridade'] ?? 'baixa'; ?>
                    <option value="baixa" <?= $prioridade == 'baixa' ? 'selected' : '' ?>>Baixa</option>
                    <option value="media" <?= $prioridade == 'media' ? 'selected' : '' ?>>Média</option>
                    <option value="alta" <?= $prioridade == 'alta' ? 'selected' : '' ?>>Alta</option>
                </select>
            </div>
            <?php endif; ?>
        </div>
        <div class="mt-4">
            <button type="button" class="btn btn-primary next-tab" data-next="itens">
                <i class="bi bi-arrow-right"></i> Próximo: Peças/Itens
            </button>
        </div>
    </div>
</div>