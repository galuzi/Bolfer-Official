Patch minimo para liberar a API de produtos do desktop no host.

Envie estes arquivos preservando a mesma estrutura:

- index.php
- .htaccess
- public_html/index.php
- app/Controllers/Api/Desktop/CategoriesController.php
- app/Controllers/Api/Desktop/ProductsController.php
- app/Repositories/ProductRepository.php
- app/Services/ProductAccountMediaService.php
- app/Support/DesktopApiPresenter.php
- sql/changes_only.sql

Antes de testar compra minima:
1. Envie os arquivos para o host preservando a estrutura.
2. Execute o SQL em sql/changes_only.sql no banco online.

Depois de subir, teste:
https://example.com/api/desktop/products
https://example.com/api/desktop/categories

Resposta esperada sem token:
401 Nao autenticado.
