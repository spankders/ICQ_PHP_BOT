<?php
//Конфигурация
include_once "config.php";
//Классы
include_once "lib/WebIcqPro.class.php";

//Подключаем команды

//----------

////

function parse_string ($string)
{
                $result = null;
                $pattern = '/(\w*)/'; 
                preg_match_all($pattern, $string, $matches);                 
                
                foreach ($matches['0'] as $item)
                {
                    if (strlen($item))
                    {
                        $result[] = $item;
                    }
                }
                return $result;
}

////
$icq = new WebIcqPro(); //Создаем класс

if ($icq->Connect(globalConfig::$bot_uin,globalConfig::$bot_password))
{
    $icq->setStatus();
    echo "Connect. Ready to Work. \n";
    $icq->sendMessage(globalConfig::$bot_uin, "");
    $i = 1;
    $fh = fopen("log,txt", 'a+');
    while ($icq->isConnected())
    {
            $message = $icq->readMessage();
            if ($message)
            {
                echo '<pre>';
                print_r($message);
                echo '</pre>';
				
                $command = parse_string ($message['message']);
                
                echo '<pre>';
                print_r($command);
                echo '</pre>';           
				$icq->sendMessage($message['from'], "Неизвестная операция. Для просмотра справки используйте команду <справка> или <help>");
			}
        sleep(1);
    }
    fclose($fh);
}
else
{
    echo "Error - ".$icq->error."\n";;
}

?>