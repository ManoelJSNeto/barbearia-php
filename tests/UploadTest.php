<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/upload.php';

/**
 * Testes de upload seguro — Fase 5
 *
 * Cobre:
 *  - Arquivo não enviado → erro
 *  - Arquivo muito grande → erro
 *  - MIME inválido (ex: texto como JPG) → erro
 *  - Imagem JPEG válida → sucesso + arquivo criado
 *  - Imagem PNG válida → sucesso
 *  - remover_upload() → remove arquivo e não estoura com path inválido
 *  - Path traversal → não remove arquivo fora de /uploads/
 */
final class UploadTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        // Cria diretório temporário para arquivos de teste
        $this->tmpDir = sys_get_temp_dir() . '/navalha_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Remove arquivos temporários criados durante os testes
        if (is_dir($this->tmpDir)) {
            array_map('unlink', glob($this->tmpDir . '/*') ?: []);
            rmdir($this->tmpDir);
        }
        // Remove uploads de teste
        $uploadDir = dirname(__DIR__) . '/uploads/test_unit/';
        if (is_dir($uploadDir)) {
            array_map('unlink', glob($uploadDir . '*') ?: []);
            @rmdir($uploadDir);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────

    /** Simula um elemento de $_FILES com um arquivo temporário real. */
    private function makeFakeFile(string $conteudo, int $tamanho = -1): array
    {
        $tmp = $this->tmpDir . '/' . uniqid() . '.tmp';
        file_put_contents($tmp, $conteudo);
        $size = $tamanho >= 0 ? $tamanho : strlen($conteudo);
        return [
            'tmp_name' => $tmp,
            'size'     => $size,
            'name'     => 'test.jpg',
            'type'     => 'image/jpeg',
            'error'    => UPLOAD_ERR_OK,
        ];
    }

    /** Cria uma imagem JPEG real mínima (1x1 px) e retorna o array de $_FILES. */
    private function makeRealJpeg(): array
    {
        $tmp = $this->tmpDir . '/real.jpg';
        $img = imagecreatetruecolor(1, 1);
        imagejpeg($img, $tmp, 80);
        imagedestroy($img);
        return [
            'tmp_name' => $tmp,
            'size'     => filesize($tmp),
            'name'     => 'real.jpg',
            'type'     => 'image/jpeg',
            'error'    => UPLOAD_ERR_OK,
        ];
    }

    /** Cria uma imagem PNG real mínima (1x1 px). */
    private function makeRealPng(): array
    {
        $tmp = $this->tmpDir . '/real.png';
        $img = imagecreatetruecolor(1, 1);
        imagepng($img, $tmp);
        imagedestroy($img);
        return [
            'tmp_name' => $tmp,
            'size'     => filesize($tmp),
            'name'     => 'real.png',
            'type'     => 'image/png',
            'error'    => UPLOAD_ERR_OK,
        ];
    }

    // ── Testes ───────────────────────────────────────────────────

    public function test_arquivo_nao_enviado_retorna_erro(): void
    {
        $result = salvar_imagem([], 'test_unit');
        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['erro']);
    }

    public function test_arquivo_muito_grande_retorna_erro(): void
    {
        $file          = $this->makeFakeFile('conteudo qualquer', 6_000_000); // > 5MB
        $result        = salvar_imagem($file, 'test_unit', 1200, 5_242_880);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsStringIgnoringCase('grande', $result['erro']);
    }

    public function test_mime_invalido_retorna_erro(): void
    {
        // Arquivo com conteúdo de texto mas simulado como imagem
        $file   = $this->makeFakeFile('isto nao e uma imagem');
        $result = salvar_imagem($file, 'test_unit');
        $this->assertFalse($result['ok']);
        $this->assertStringContainsStringIgnoringCase('format', strtolower($result['erro']));
    }

    public function test_jpeg_valido_retorna_sucesso_e_cria_arquivo(): void
    {
        $file   = $this->makeRealJpeg();
        $result = salvar_imagem($file, 'test_unit');

        $this->assertTrue($result['ok'], 'JPEG válido deve ser salvo: ' . $result['erro']);
        $this->assertEmpty($result['erro']);
        $this->assertStringEndsWith('.webp', $result['path']); // sempre converte para webp
        $this->assertFileExists(dirname(__DIR__) . '/uploads/' . $result['path']);

        // Limpa o arquivo criado
        @unlink(dirname(__DIR__) . '/uploads/' . $result['path']);
    }

    public function test_png_valido_retorna_sucesso(): void
    {
        $file   = $this->makeRealPng();
        $result = salvar_imagem($file, 'test_unit');

        $this->assertTrue($result['ok'], 'PNG válido deve ser salvo: ' . $result['erro']);
        $this->assertStringEndsWith('.webp', $result['path']);

        @unlink(dirname(__DIR__) . '/uploads/' . $result['path']);
    }

    public function test_path_do_arquivo_salvo_tem_formato_correto(): void
    {
        $file   = $this->makeRealJpeg();
        $result = salvar_imagem($file, 'test_unit');

        $this->assertTrue($result['ok']);
        // Formato esperado: "test_unit/<32 chars hex>.webp"
        $this->assertMatchesRegularExpression(
            '/^test_unit\/[a-f0-9]{32}\.webp$/',
            $result['path']
        );

        @unlink(dirname(__DIR__) . '/uploads/' . $result['path']);
    }

    public function test_remover_upload_remove_arquivo_existente(): void
    {
        // Cria arquivo falso dentro de uploads/
        $dir  = dirname(__DIR__) . '/uploads/test_unit/';
        @mkdir($dir, 0755, true);
        $nome = 'arquivo_teste_' . uniqid() . '.webp';
        file_put_contents($dir . $nome, 'fake');

        $this->assertFileExists($dir . $nome);
        remover_upload('test_unit/' . $nome);
        $this->assertFileDoesNotExist($dir . $nome);
    }

    public function test_remover_upload_nao_remove_arquivo_fora_de_uploads(): void
    {
        // Tenta path traversal — não deve deletar arquivo fora de /uploads/
        $arquivoFora = sys_get_temp_dir() . '/navalha_nao_deletar_' . uniqid() . '.txt';
        file_put_contents($arquivoFora, 'protegido');

        remover_upload('../../' . basename(sys_get_temp_dir()) . '/' . basename($arquivoFora));

        $this->assertFileExists($arquivoFora, 'Path traversal não deve deletar arquivos fora de /uploads/.');
        unlink($arquivoFora);
    }

    public function test_remover_upload_com_path_inexistente_nao_lanca_excecao(): void
    {
        // Não deve lançar exceção nem warning
        $this->expectNotToPerformAssertions();
        remover_upload('test_unit/arquivo_que_nao_existe.webp');
    }
}
