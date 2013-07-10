<?php 
class Test
{
	public function service1()
	{
		while(true)
		{
			echo "service1\n";
			sleep(3);
			throw new Exception("service1 throw exception");
		}
	}


	public function service2()
	{
		while(true)
		{
			echo "service2\n";
			sleep(3);
			echo "kill service2\n";
			die();

		}
	}

	public function service3($param)
	{
		while(true)
		{
			echo "service3 $param \n";
			sleep(3);
		}
	}

}

