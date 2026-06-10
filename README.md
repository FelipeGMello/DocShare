# DocShare
Documentos compartilhados usando Conflict-free Replicated Data Type (CRDT)
Existe uma apresentação em pdf, usada para apresentar o projeto na faculdade, explicando o conceito de CRDT e apresentando um diagrama de componentes bem mal-feito/incompleto.

# Pipeline

Há um listener em javascript que, a cada digitação feita pelo usuário, salva essa alteração e manda para o servidor laravel, HTTP POST.
O servidor recebe e através do DocumentController, envia para o servidor em Rust, que, por sua vez, processa essas alterações feitas pelo usuário.
Após o processamento, o servidor Rust guarda em memória e manda o documento somando as partes antigas com as mudanças novas devolta para o servidor Laravel.
O Laravel, através do Reverb faz um multicast, para todos os clientes conectados com ele no momento, do documento atualizado.
Toda a comunicação é feito usando JSON.

Em geral, o usuário manda o que ele tem para o servidor e, de tempos em tempos, recebe o que outro usuário tem somados com o que ele já tem. 
Eventualmente, todos os usuários terão a mesma versão do documento.

## Observação
Como eu e meu amigo tivemos pouco tempo e menos conhecimento ainda, tivemos que utilizar IA para nos ajudar com o código. Portanto, estejam avisados, muitas linhas foram feitas por IA.
