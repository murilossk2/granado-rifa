<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Números Colaborativos</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            padding: 20px;
            background-color: #f0f0f0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(50px, 1fr));
            gap: 10px;
        }

        .number-box {
            background-color: white;
            border: 2px solid #ccc;
            padding: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            min-height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9em;
            border-radius: 8px;
            position: relative;
        }

        .number-box.selected-green {
            background-color: #90EE90;
            border-color: #006400;
        }

        .number-box.selected-orange {
            background-color: orange;
            border-color: darkorange;
        }

        .reset-btn {
            position: absolute;
            top: 2px;
            right: 2px;
            width: 15px;
            height: 15px;
            background: red;
            color: white;
            border-radius: 50%;
            font-size: 10px;
            display: none;
            cursor: pointer;
            line-height: 15px;
        }

        .number-box:hover .reset-btn {
            display: block;
        }

        .number-box:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        @media (max-width: 768px) {
            .grid {
                grid-template-columns: repeat(auto-fill, minmax(40px, 1fr));
            }
            .number-box {
                min-height: 40px;
                font-size: 0.8em;
            }
        }

        @media (max-width: 480px) {
            .grid {
                grid-template-columns: repeat(auto-fill, minmax(30px, 1fr));
                gap: 5px;
            }
            .number-box {
                min-height: 30px;
                font-size: 0.7em;
                padding: 5px;
            }
            .reset-btn {
                width: 12px;
                height: 12px;
                font-size: 8px;
                line-height: 12px;
            }
        }
    </style>
    
    <!-- Adicione o SDK do Supabase -->
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
</head>
<body>
    <div class="container">
        <div class="grid" id="gridContainer"></div>
    </div>

    <script>
        // Configuração do Supabase (substitua com suas credenciais)
        const supabaseUrl = 'https://qliepomnbkdtmdygvbpp.supabase.co';
        const supabaseKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InFsaWVwb21uYmtkdG1keWd2YnBwIiwicm9sZSI6ImFub24iLCJpYXQiOjE3MzgxNTMyNzksImV4cCI6MjA1MzcyOTI3OX0.OSxL_MT9l_HMKOGPJAwDqhIRG05DSag2xpjaK_2a_9U';
        const supabase = supabase.createClient(supabaseUrl, supabaseKey);

        window.addEventListener('load', async () => {
            const gridContainer = document.getElementById('gridContainer');

            // Criação dos números
            for (let i = 1; i <= 1000; i++) {
                const box = document.createElement('div');
                box.className = 'number-box';
                box.textContent = i;
                box.dataset.number = i;

                // Botão de reset
                const resetBtn = document.createElement('div');
                resetBtn.className = 'reset-btn';
                resetBtn.innerHTML = '×';
                resetBtn.onclick = async (e) => {
                    e.stopPropagation();
                    if (confirm(`Resetar número ${i}?`)) {
                        await supabase
                            .from('numbers')
                            .update({ state: 0 })
                            .eq('number', i);
                    }
                };
                box.appendChild(resetBtn);

                // Listener para atualizações em tempo real
                supabase
                    .channel('custom-all-channel')
                    .on(
                        'postgres_changes',
                        { event: '*', schema: 'public', table: 'numbers', filter: `number=eq.${i}` },
                        (payload) => {
                            const newState = payload.new.state;
                            box.className = 'number-box';
                            if (newState === 1) {
                                box.classList.add('selected-green');
                            } else if (newState === 2) {
                                box.classList.add('selected-orange');
                            }
                        }
                    )
                    .subscribe();

                // Carregar estado inicial
                const { data } = await supabase
                    .from('numbers')
                    .select('state')
                    .eq('number', i)
                    .single();

                if (data) {
                    if (data.state === 1) box.classList.add('selected-green');
                    else if (data.state === 2) box.classList.add('selected-orange');
                }

                // Listener para cliques
                box.addEventListener('click', async (e) => {
                    e.preventDefault();
                    const { data } = await supabase
                        .from('numbers')
                        .select('state')
                        .eq('number', i)
                        .single();

                    const currentState = data ? data.state : 0;
                    let novaCor = 0;
                    let mensagem = '';

                    if (currentState === 0) {
                        mensagem = `Marcar número ${i} como verde?`;
                        novaCor = 1;
                    } else if (currentState === 1) {
                        mensagem = `Alterar número ${i} para laranja?`;
                        novaCor = 2;
                    } else {
                        mensagem = `Desmarcar número ${i}?`;
                        novaCor = 0;
                    }

                    if (confirm(mensagem)) {
                        await supabase
                            .from('numbers')
                            .upsert({ number: i, state: novaCor });
                    }
                });

                gridContainer.appendChild(box);
            }

            // Cria documentos iniciais se não existirem
            const { data: existingNumbers } = await supabase
                .from('numbers')
                .select('number');

            const existingNumbersSet = new Set(existingNumbers.map(n => n.number));
            const numbersToInsert = [];

            for (let i = 1; i <= 1000; i++) {
                if (!existingNumbersSet.has(i)) {
                    numbersToInsert.push({ number: i, state: 0 });
                }
            }

            if (numbersToInsert.length > 0) {
                await supabase
                    .from('numbers')
                    .insert(numbersToInsert);
            }
        });
    </script>
</body>
</html>