<?php 
/**
 * 创建守护进程守护多个服务
 * 
 * 用法实例： 
    require("ProcSupervise.php");
    require("Test.php");
    $d = new ProcSupervise();
    $task_list = array(
        array('class_name'=>'test', 'method_name'=>'func1'),
        array('class_name'=>'test', 'method_name'=>'func2'),
        array('class_name'=>'test', 'method_name'=>'func3', 'params'=>array("my param")),
    );
    $d->setTaskList($task_list);
    $d->run();
 * 
 * @author zhou_hongwei@126.com
 * @date   2013-07-10
 */
declare(ticks = 1);
class ProcSupervise
{
    private $task_list;
    private $pid_to_task;
    private $log_dir ;
    private $log_file_name;
    private $quit;

    public function __construct()
    {
        $this->log_dir = dirname(__FILE__);
        $this->log_file_name = 'daemon_exec.log';
        $this->task_list = array();
        $this->pid_to_task = array();
        $this->quit = true;
        pcntl_signal(SIGTERM, array($this, 'signalHandler'));
        pcntl_signal(SIGINT, array($this, 'signalHandler'));
        pcntl_signal(SIGCHLD, array($this, 'signalHandler'));
    }


    public function setTaskList($task_list)
    {
        $this->task_list = $task_list;
    }

    public function setLogDir($log_dir)
    {
        $this->log_dir = $log_dir;
    }

    public function run()
    {
        //初始化日志模块
        if(!$this->logInit())
        {
            echo "初始化日志功能失败，请检查日志存放目录是否存在并且有写权限\n";
            exit(1);
        }
        
        //启动子任务
        foreach($this->task_list as $task)
        {
            $this->doTask($task);
        }
        
        //检查子任务启动状态
        sleep(3); 
        if(!$this->checkChildren())
        {
            $this->killAllTasks(SIGKILL);
            echo "启动进程" . $this->getTaskDesc($task) . "失败\n";
            $this->log("INFO: 守护进程退出");
            exit(1);
        }
        
        //监控子任务执行状态
        $this->observeTasks();
    }

    private function checkChildren()
    {
        return count($this->pid_to_task) === count($this->task_list);
    }

    private function observeTasks()
    {
        $this->quit = false;
        while(true) {
            if($this->quit)
            {
                $this->killAllTasks(SIGKILL);
                $this->log("INFO: 守护进程退出");
                exit(0);
            }
            sleep(1);
        }
    }

    private function getTaskDesc($task)
    {
        $class_name = isset($task['class_name'])? $task['class_name']: 'unknow';
        $method_name = isset($task['method_name'])? $task['method_name']: 'unknow';
        $params = isset($task['params'])? json_encode($task['params']): '';
        $desc = "类{$class_name},方法{$method_name}, 参数{$params}";
        return $desc;
    }

    /**
     * 杀死所有的子进程
     */
    private function killAllTasks($signo)
    {
        if ($signo == SIGTERM || $signo == SIGKILL)
        {
            foreach ($this->pid_to_task as $pid => $value)
            {
                posix_kill($pid, $signo);
            }
            //等待所有的子进程结束
            while(count($this->pid_to_task) > 0)
            {
                sleep(1);
            }
        }
    }

    /**
     * 执行任务
     */
    private function doTask($task)
    {
        $pid = pcntl_fork();
        if($pid === -1)
        {
            $this->log("ERROR: fork任务" . $this->getTaskDesc($task) . "失败");
        }
        else if($pid === 0)
        {
            $this->callTaskMethod($task);
            exit(1);
        }
        else 
        {
            $this->pid_to_task[$pid] = $task;
        }
    }

    private function callTaskMethod($task)
    {
        $this->log('INFO: ' . $this->getTaskDesc($task) . '开始执行');
        $class_name = $task['class_name'];
        $method_name = $task['method_name'];
        $params = isset($task['params'])? $task['params'] : null;
        if ($class_name && $method_name)
        {
            if (class_exists($class_name))
            {
                $task_handler = new $class_name;
                if (method_exists($task_handler, $method_name))
                {
                    if (!empty($params))
                    {
                        $res = call_user_func_array(array($task_handler, $method_name), $params);
                    }
                    else
                    {
                        $res = call_user_func(array($task_handler, $method_name));
                    }
                    if(false === $res)
                    {
                        $this->log("ERROR: 启动任务" . $this->getTaskDesc($task) . "失败");
                    }
                }
            }
            else
            {
                $this->log("ERROR: 类{$class_name}不存在");
            }
        }
        else
        {
            $this->log("ERROR: 任务类或者类方法未设置");
        }
    }

    public function signalHandler($signo)
    {
        switch ($signo)
        {
        case SIGTERM:
        case SIGINT:
            $this->quit = true;
            break;
        case SIGCHLD:
            while(($pid = pcntl_waitpid(-1, $status, WNOHANG)) > 0)
            {
                if(isset($this->pid_to_task[$pid]))  
                {
                    $this->log("INFO: " . $this->getTaskDesc($this->pid_to_task[$pid]) . "任务退出, 退出状态是{$status}");
                    if(!$this->quit)
                    {
                        $this->doTask($this->pid_to_task[$pid]);
                    }
                    unset($this->pid_to_task[$pid]);
                }
            }
            break;
        }
    }
    
    public function logInit()
    {
        if(!@file_exists($this->log_dir))
        {
            return false;
        }
        if(!@touch($this->log_dir.'/' . $this->log_file_name))
        {
            return false;
        }
        return true;
    }

    private function log($log_content)
    {
        $now = date('Y-m-d H:i:s');
        $log_str = $now."\t\t{$log_content}\n";
        if(!@file_exists($this->log_dir.'/' . $this->log_file_name))
        {
            @file_put_contents($this->log_dir.'/' . $this->log_file_name, $log_str);
        }
        else
        {
            @file_put_contents($this->log_dir.'/' . $this->log_file_name, $log_str, FILE_APPEND);
        }
    }

}

