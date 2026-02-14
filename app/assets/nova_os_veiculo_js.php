<script>
$(document).ready(function() {
    // Array para armazenar peças selecionadas
    window.pecasSelecionadas = {
        lista: [],
        manuais: []
    };
    
    // Array para armazenar fotos
    window.fotosSelecionadas = [];
    
    // CORREÇÃO: Navegação entre tabs correta
    $('.next-tab').on('click', function(e) {
        e.preventDefault();
        let nextTab = $(this).data('next');
        
        // Validar antes de mudar
        if (nextTab === 'itens') {
            if (!validarDadosVeiculo()) return;
        } else if (nextTab === 'fotos') {
            if (!validarPecas()) {
                alert('Selecione pelo menos uma peça da lista OU informe peças manualmente!');
                return;
            }
        }
        
        // Usar Bootstrap para mudar tab
        let tabElement = document.getElementById(nextTab + '-tab');
        if (tabElement) {
            let tab = new bootstrap.Tab(tabElement);
            tab.show();
        }
        
        if (nextTab === 'revisao') {
            setTimeout(atualizarRevisao, 100);
        }
    });
    
    $('.prev-tab').on('click', function(e) {
        e.preventDefault();
        let prevTab = $(this).data('prev');
        let tabElement = document.getElementById(prevTab + '-tab');
        if (tabElement) {
            let tab = new bootstrap.Tab(tabElement);
            tab.show();
        }
    });
    
    // Mostrar/ocultar campo "outra" marca
    $('#select-marca').change(function() {
        let outraInput = $('#input-marca-outra');
        if ($(this).val() === 'outra') {
            outraInput.show().prop('required', true);
        } else {
            outraInput.hide().prop('required', false).val('');
        }
    });
    
    // CORREÇÃO: Buscar peças funcionando
    $('#buscar-peca').on('input', function() {
        let termo = $(this).val().toLowerCase().trim();
        if (termo.length < 2) {
            $('.peca-card').parent().show();
            return;
        }
        
        $('.peca-card').each(function() {
            let nome = $(this).find('strong').text().toLowerCase();
            let codigo = $(this).find('.text-muted').text().toLowerCase();
            let mostra = nome.includes(termo) || codigo.includes(termo);
            $(this).parent().toggle(mostra);
        });
    });
    
    // Filtrar por categoria
    $('#filtro-categoria').change(function() {
        let categoria = $(this).val();
        if (categoria) {
            $('.peca-card').parent().hide();
            $('.categoria-' + categoria).parent().show();
        } else {
            $('.peca-card').parent().show();
        }
    });
    
    // Gerenciar peças da lista
    $(document).on('change', '.peca-checkbox', function() {
        let pecaId = $(this).val();
        let pecaNome = $(this).data('nome');
        let pecaCategoria = $(this).data('categoria');
        
        if ($(this).is(':checked')) {
            let existe = window.pecasSelecionadas.lista.some(p => p.id == pecaId);
            if (!existe) {
                window.pecasSelecionadas.lista.push({
                    id: pecaId,
                    nome: pecaNome,
                    categoria: pecaCategoria,
                    tipo: 'lista'
                });
            }
        } else {
            window.pecasSelecionadas.lista = window.pecasSelecionadas.lista.filter(p => p.id != pecaId);
        }
        
        atualizarListaSelecionadas();
        validarPecas();
    });
    
    // Adicionar peças manuais
    $('#adicionar-manuais').on('click', function() {
        let texto = $('#pecas-manuais').val().trim();
        if (!texto) {
            alert('Digite pelo menos uma peça!');
            return;
        }
        
        let linhas = texto.split('\n');
        let adicionadas = 0;
        
        linhas.forEach(function(linha, index) {
            linha = linha.trim();
            if (linha) {
                let existe = window.pecasSelecionadas.manuais.some(p => p.nome === linha);
                if (!existe) {
                    window.pecasSelecionadas.manuais.push({
                        id: 'manual_' + Date.now() + '_' + index,
                        nome: linha,
                        categoria: 'Manual',
                        tipo: 'manual'
                    });
                    adicionadas++;
                }
            }
        });
        
        if (adicionadas > 0) {
            atualizarListaSelecionadas();
            validarPecas();
            $('#pecas-manuais').val('');
            alert(adicionadas + ' peça(s) adicionada(s)!');
        }
    });
    
    // Remover peça
    $(document).on('click', '.btn-remover-peca', function() {
        let pecaId = $(this).data('id');
        let tipo = $(this).data('tipo');
        
        if (tipo === 'lista') {
            $('#peca_' + pecaId).prop('checked', false);
            window.pecasSelecionadas.lista = window.pecasSelecionadas.lista.filter(p => p.id != pecaId);
        } else {
            window.pecasSelecionadas.manuais = window.pecasSelecionadas.manuais.filter(p => p.id != pecaId);
        }
        
        atualizarListaSelecionadas();
        validarPecas();
    });
    
    // Limpar todas as peças
    $('#limpar-todas').on('click', function() {
        if (confirm('Remover todas as peças?')) {
            $('.peca-checkbox').prop('checked', false);
            window.pecasSelecionadas.lista = [];
            window.pecasSelecionadas.manuais = [];
            $('#pecas-manuais').val('');
            atualizarListaSelecionadas();
            validarPecas();
        }
    });
    
    // Upload de fotos
    $('#btn-selecionar-fotos').on('click', function() {
        $('#input-fotos').click();
    });
    
    $('#input-fotos').on('change', function(e) {
        window.fotosSelecionadas = Array.from(this.files);
        atualizarPreviewFotos();
    });
    
    // Remover foto
    $(document).on('click', '.btn-remover-foto', function() {
        let index = $(this).data('index');
        window.fotosSelecionadas.splice(index, 1);
        
        let dataTransfer = new DataTransfer();
        window.fotosSelecionadas.forEach(file => dataTransfer.items.add(file));
        $('#input-fotos')[0].files = dataTransfer.files;
        
        atualizarPreviewFotos();
    });
    
    // Adicione antes do $('#form-os').on('submit')
function adicionarCamposHiddenManuais() {
    // Remover campos hidden antigos
    $('input[name^="peca_manual_"]').remove();
    
    // Adicionar campos hidden para cada peça manual
    window.pecasSelecionadas.manuais.forEach(function(peca, index) {
        let input = '<input type="hidden" name="peca_manual_' + index + '" value="' + peca.nome + '">';
        $('#form-os').append(input);
    });
    
    // Adicionar campo com total de peças manuais
    $('#form-os').append('<input type="hidden" name="total_pecas_manuais" value="' + window.pecasSelecionadas.manuais.length + '">');
    }

    // Modifique o submit handler
    $('#form-os').on('submit', function(e) {
        if (!validarDadosVeiculo()) {
            e.preventDefault();
            document.getElementById('dados-tab').click();
            return false;
        }
        
        if (!validarPecas()) {
            e.preventDefault();
            alert('Selecione pelo menos uma peça!');
            document.getElementById('itens-tab').click();
            return false;
        }
        
        // Adicionar campos hidden para peças manuais
        adicionarCamposHiddenManuais();
        
        $('#btn-enviar-os').prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Enviando...');
        return true;
    });
    
    // Funções auxiliares
    function validarDadosVeiculo() {
        let campos = ['placa', 'eb', 'chassi', 'modelo'];
        let erros = [];
        
        campos.forEach(function(campo) {
            let valor = $('[name="' + campo + '"]').val().trim();
            if (!valor) erros.push(campo.charAt(0).toUpperCase() + campo.slice(1) + ' é obrigatório');
        });
        
        if (!$('#select-marca').val()) erros.push('Marca é obrigatória');
        if ($('#select-marca').val() === 'outra' && !$('#input-marca-outra').val().trim()) {
            erros.push('Informe a marca');
        }
        if (!$('[name="ano"]').val()) erros.push('Ano é obrigatório');
        
        if (erros.length) {
            alert('Corrija os erros:\n• ' + erros.join('\n• '));
            return false;
        }
        return true;
    }
    
    function validarPecas() {
        let total = window.pecasSelecionadas.lista.length + window.pecasSelecionadas.manuais.length;
        $('#btn-proximo-fotos').prop('disabled', total === 0);
        return total > 0;
    }
    
    function atualizarListaSelecionadas() {
        let lista = $('#lista-selecionadas');
        lista.empty();
        
        let todasPecas = [...window.pecasSelecionadas.lista, ...window.pecasSelecionadas.manuais];
        
        if (todasPecas.length === 0) {
            $('#nenhuma-peca-mensagem').show();
            lista.hide();
        } else {
            $('#nenhuma-peca-mensagem').hide();
            lista.show();
            
            todasPecas.forEach(function(peca) {
                let badge = peca.tipo === 'lista' ? 
                    '<span class="badge bg-primary me-1">Lista</span>' : 
                    '<span class="badge bg-warning me-1">Manual</span>';
                
                let item = '<li class="list-group-item d-flex justify-content-between align-items-center">' +
                    '<div><strong>' + peca.nome + '</strong><br>' +
                    '<small class="text-muted">' + badge + 
                    (peca.tipo === 'lista' ? 'Categoria: ' + peca.categoria : 'Informada manualmente') +
                    '</small></div>' +
                    '<button type="button" class="btn btn-sm btn-outline-danger btn-remover-peca" ' +
                    'data-id="' + peca.id + '" data-tipo="' + peca.tipo + '">' +
                    '<i class="bi bi-trash"></i></button></li>';
                
                lista.append(item);
            });
        }
    }
    
    function atualizarPreviewFotos() {
        let container = $('#preview-container');
        container.empty();
        
        if (window.fotosSelecionadas.length) {
            $('#upload-status').html('<div class="alert alert-info">' + 
                window.fotosSelecionadas.length + ' foto(s) selecionada(s)</div>');
            
            window.fotosSelecionadas.forEach(function(file, index) {
                let reader = new FileReader();
                reader.onload = function(e) {
                    let col = '<div class="col-md-3 mb-3">' +
                        '<div class="card foto-preview">' +
                        '<img src="' + e.target.result + '" class="card-img-top" style="height:150px;object-fit:cover">' +
                        '<div class="card-body p-2">' +
                        '<small class="card-text text-truncate d-block" title="' + file.name + '">' + file.name + '</small>' +
                        '<small class="text-muted">' + formatBytes(file.size) + '</small>' +
                        '<button type="button" class="btn btn-sm btn-danger w-100 mt-2 btn-remover-foto" data-index="' + index + '">' +
                        '<i class="bi bi-trash"></i> Remover</button>' +
                        '</div></div></div>';
                    
                    container.append(col);
                };
                reader.readAsDataURL(file);
            });
        } else {
            $('#upload-status').html('');
        }
    }
    
    function atualizarRevisao() {
        // Dados do veículo
        let placa = $('[name="placa"]').val();
        let eb = $('[name="eb"]').val();
        let chassi = $('[name="chassi"]').val();
        let marca = $('#select-marca').val() === 'outra' ? 
            $('#input-marca-outra').val() : $('#select-marca').val();
        let modelo = $('[name="modelo"]').val();
        let ano = $('[name="ano"]').val();
        let cor = $('[name="cor"]').val() || 'Não informada';
        
        $('#revisao-placa').text(placa);
        $('#revisao-eb').text(eb);
        $('#revisao-chassi').text(chassi);
        $('#revisao-marca-modelo').text(marca + ' ' + modelo);
        $('#revisao-ano-cor').text(ano + ' / ' + cor);
        
        // Peças
        let todasPecas = [...window.pecasSelecionadas.lista, ...window.pecasSelecionadas.manuais];
        let revisaoDiv = $('#revisao-pecas');
        
        if (todasPecas.length) {
            let html = '<div class="mb-2"><strong>Total: ' + todasPecas.length + ' peça(s)</strong></div>' +
                '<div style="max-height:200px;overflow-y:auto;"><ul class="list-group">';
            
            todasPecas.forEach(function(peca) {
                let icon = peca.tipo === 'lista' ? 'bi-check-circle text-success' : 'bi-pencil text-warning';
                html += '<li class="list-group-item py-2">' +
                    '<i class="bi ' + icon + ' me-2"></i>' +
                    '<strong>' + peca.nome + '</strong>' +
                    '<small class="text-muted ms-2">(' + 
                    (peca.tipo === 'lista' ? peca.categoria : 'Manual') + ')</small>' +
                    '</li>';
            });
            
            html += '</ul></div>';
            revisaoDiv.html(html);
        }
        
        // Fotos
        let fotosDiv = $('#revisao-fotos');
        if (window.fotosSelecionadas.length) {
            fotosDiv.html('<div class="mb-2"><strong>' + window.fotosSelecionadas.length + ' foto(s)</strong></div>');
        } else {
            fotosDiv.html('<p class="text-muted mb-0">Nenhuma foto selecionada</p>');
        }
    }
    
    function formatBytes(bytes) {
        if (bytes === 0) return '0 Bytes';
        let k = 1024;
        let sizes = ['Bytes', 'KB', 'MB', 'GB'];
        let i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    // Inicializar
    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
    $('#select-marca').trigger('change');
    <?php if (!empty($_POST['itens'])): ?>
        <?php foreach ($_POST['itens'] as $item_id): ?>
        $('#peca_<?= $item_id ?>').prop('checked', true).trigger('change');
        <?php endforeach; ?>
    <?php endif; ?>
    <?php endif; ?>
});
</script>