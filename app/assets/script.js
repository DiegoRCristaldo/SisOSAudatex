document.addEventListener("DOMContentLoaded", function() {
    const formComentario = document.querySelector("#formComentario");

    if (formComentario) {
        formComentario.addEventListener("submit", function(e) {
            e.preventDefault();

            fetch("comentarios.php", {
                method: "POST",
                body: new FormData(formComentario)
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === "sucesso") {
                    alert("Comentário enviado!");
                    location.reload();
                } else {
                    alert("Erro: " + data.mensagem);
                }
            });
        });
    }
});
