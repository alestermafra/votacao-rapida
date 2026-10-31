# Sistema de votação

## Motivação

No dia 11/3 na reunião do CO (COCEX ???) foi utilizado um sistema de votação eletrônica para eleição fechada (votos anônimos). Cada membro da reunião recebeu um papelete com um qrcode que direcionava para um formulário google que apresentava a cédula de votação. O diretor da EESC gostou do sistema e solicitou que fosse implantado um sistema similar em votação a ser realizada na Econ da unidade. A votação eletrônica foi realizada utilizando google forms com várias regras de geração de tokens, validação dos votos, totalização, etc, muitos desses passos sendo feito manualmente. Em reunião (google meet) realizada no dia 17/3/2020 com o Bruno, verificamos a viabilidade de se desenvolver um site em PHP a fim de atender a essa finalidade e permitir a automação de muitas das tarefas manuais empregadas até então.

## Objetivo

O objetivo desse sistema é fornecer uma plataforma de votação eletrônica a fim de atender reuniões de colegiados. A votação ocorre por meio de um token que identifica cada voto. O token pode ser ou não associado à uma pessoa. Em reuniões presenciais o token é distribuído em papel com QRCode, em reuniões por videoconferencia o token é distribuído por email.

## Dependências

* PHP 7.2
* Apache
* Composer 1.x
* ext-curl

## Atenção
Mesmo em dev, não utilize o servidor interno do PHP pois ele é monotarefa e o sistema precisa de pelo menos dois processos ativos para funcionar.

# Instalação utilizando Docker

Este tutorial explica como configurar e rodar o sistema utilizando Docker. O arquivo `env` já está configurado para facilitar a instalação.

---

## **Pré-requisitos**
Certifique-se de que você possui o Docker e o Docker Compose instalados. Para verificar, execute os seguintes comandos:

```bash
docker --version
docker compose version
```
Se não estiverem instalados, siga as instruções na [documentação oficial do Docker](https://docs.docker.com/get-docker/) para instalá-los.

---

## **Passos para Instalação**

1. **Clone o repositório**:
   ```bash
   git clone https://github.com/alestermafra/votacao-rapida.git
   ```

2. **Acesse a pasta do repositório**:
   ```bash
   cd votacao-rapida
   ```

3. **Copie o arquivo `env` para `.env`**:
   ```bash
   cp env .env
   ```
   O arquivo `.env` está configurado para rodar com Docker por padrão.

4. **Crie os contêineres**:
   ```bash
   docker compose up -d
   ```
   Após isso, verifique se os contêineres estão rodando com o comando:
   ```bash
   docker ps
   ```
   Você deve ver algo similar a:
   ```
    CONTAINER ID   IMAGE                   COMMAND                  CREATED          STATUS                    PORTS
    c6b31874c1b3   votacao-rapida-app      "docker-php-entrypoi…"   17 minutes ago   Up 17 minutes             0.0.0.0:8000->80/tcp, [::]:8000->80/tcp
    9fac5f526730   mysql:5.7               "docker-entrypoint.s…"   17 minutes ago   Up 17 minutes             0.0.0.0:3306->3306/tcp, :::3306->3306/tcp, 33060/tcp
    4bb58be3b879   axllent/mailpit:v1.21   "/mailpit"               17 minutes ago   Up 17 minutes (healthy)   0.0.0.0:1025->1025/tcp, :::1025->1025/tcp, 0.0.0.0:8025->8025/tcp, :::8025->8025/tcp, 1110/tcp
   ```

5. **Acesse o contêiner**:
   ```bash
   docker exec -it votacao-rapida-app bash
   ```

6. **Instale as dependências**:
   ```bash
   composer install
   ```

7. **Crie a infraestrutura do banco de dados**:
   Execute os seguintes comandos:
   ```bash
   php sql/0_nuke.php sim
   php sql/1_migration_inicial.php
   php sql/2_seed_inicial.php
   php sql/3_migration_2020-06-17.php
   php sql/4_migration_2020-06-30.php
   ```
   > **Nota:** A ordem de execução é importante. Esses scripts criam e populam as tabelas do banco de dados.

8. **Ajuste as permissões**:
   Certifique-se de que o servidor web tem acesso à pasta `local`:
   ```bash
   chown -R www-data:www-data local
   ```

9. **Saia do contêiner**:
   ```bash
   exit
   ```

10. **Acesse o sistema**:
    - Acesse a aplicação: [http://localhost:8000](http://localhost:8000).
    - Para fazer o primeiro login como administrador, use a URL: [http://localhost:8000/login/99999999](http://localhost:8000/login/99999999).

---

## **Debug e Solução de Problemas**

### Verificar logs do contêiner
Se algo não funcionar como esperado, verifique os logs do contêiner:
```bash
docker logs votacao-rapida-app
```

---

## Mais informações

[Changelog](doc/changelog.md)

[Descrição funcional](doc/descricao_funcional.md)

[Descrição técnica](doc/descricao_tecnica.md)

